<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Tests\Fixtures\RecordJobContext;
use RuntimeException;
use Stancl\Tenancy\Events\TenancyInitialized;

class InfrastructureTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'filament-tenancy.queue_connection' => 'database',
            'queue.default' => 'database',
            'queue.failed.driver' => 'null',
            'queue.connections.database.connection' => null,
            'queue.connections.database.retry_after' => 420,
            'session.driver' => 'database',
            'session.connection' => null,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        Schema::create('job_contexts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('tenant_id')->nullable();
            $table->string('connection');
        });
    }

    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'tenant-provisioning,default', '--once' => true, '--sleep' => 0])->assertSuccessful();
    }

    public function test_database_worker_provisions_and_sessions_remain_central(): void
    {
        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->assertSame(ProvisioningStatus::Pending, $workspace->fresh()->status);
        $this->assertSame(1, DB::table('jobs')->count());
        $statuses = [];
        Event::listen(TenancyInitialized::class, function ($event) use (&$statuses) {
            $statuses[] = $event->tenancy->tenant->fresh()->status;
        });
        $this->work();
        $this->assertContains(ProvisioningStatus::Provisioning, $statuses);
        $this->assertSame(ProvisioningStatus::Ready, $workspace->fresh()->status);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());

        $this->actingAs($owner)->get('/admin/workspaces/acme')->assertOk();
        $this->assertSame('central', config('session.connection'));
        $this->assertSame('central', config('queue.connections.database.connection'));
        $this->assertGreaterThan(0, DB::table('sessions')->count());
        $workspace->run(function () {
            $this->assertFalse(Schema::hasTable('sessions'));
            $this->assertFalse(Schema::hasTable('jobs'));
        });
    }

    public function test_worker_recovers_from_provisioning_failure(): void
    {
        $workspace = app(CreateWorkspace::class)->create($this->user(), ['name' => 'Broken', 'slug' => 'broken']);
        config(['filament-tenancy.migration_path' => '/missing-tenant-migrations']);
        $this->work();
        $this->assertSame(ProvisioningStatus::Failed, $workspace->fresh()->status);
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());

        config(['filament-tenancy.migration_path' => __DIR__.'/Fixtures/migrations']);
        DB::table('jobs')->update(['available_at' => time() - 1]);
        $this->work();
        $this->assertSame(ProvisioningStatus::Ready, $workspace->fresh()->status);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_worker_restores_context_between_tenant_and_central_jobs(): void
    {
        $owner = $this->user();
        $a = app(CreateWorkspace::class)->create($owner, ['name' => 'A', 'slug' => 'a']);
        $b = app(CreateWorkspace::class)->create($owner, ['name' => 'B', 'slug' => 'b']);
        $this->work();
        $this->work();
        $a->run(fn () => Bus::dispatch(new RecordJobContext('A')));
        $b->run(fn () => Bus::dispatch(new RecordJobContext('B', fail: true)));
        Bus::dispatch(new RecordJobContext('central'));
        $this->assertSame(3, DB::table('jobs')->count());
        for ($i = 0; $i < 3; $i++) {
            $this->work();
            $this->assertFalse(tenancy()->initialized);
            $this->assertSame('central', DB::getDefaultConnection());
        }
        $contexts = DB::table('job_contexts')->orderBy('id')->get();
        $this->assertSame([$a->id, $b->id, null], $contexts->pluck('tenant_id')->all());
        $this->assertSame(['tenant', 'tenant', 'central'], $contexts->pluck('connection')->all());
        $a->run(fn () => $this->assertSame(['A'], DB::table('notes')->pluck('body')->all()));
        $b->run(fn () => $this->assertSame(['B'], DB::table('notes')->pluck('body')->all()));
    }

    public function test_http_exception_restores_central_context(): void
    {
        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->work();
        $this->actingAs($owner);
        Route::get('/workspace-exception', function () use ($workspace) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            Filament::setTenant($workspace, isQuiet: true);

            return app(InitializeWorkspace::class)->handle(request(), function () {
                $this->assertSame('tenant', DB::getDefaultConnection());
                throw new RuntimeException('Intentional HTTP failure');
            });
        });
        $this->get('/workspace-exception')->assertStatus(500);
        $this->assertFalse(tenancy()->initialized);
        $this->assertNull(Filament::getTenant());
        $this->assertSame('central', DB::getDefaultConnection());
    }
}
