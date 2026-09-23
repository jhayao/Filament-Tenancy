<?php

namespace Liern\FilamentTenancy\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\TenancyServiceProvider;
use Liern\FilamentTenancy\Tests\Fixtures\TestPanelProvider;
use Liern\FilamentTenancy\Tests\Fixtures\User;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use STS\FilamentImpersonate\FilamentImpersonateServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $databaseDirectory;

    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentImpersonateServiceProvider::class,
            \Stancl\Tenancy\TenancyServiceProvider::class,
            TenancyServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $this->databaseDirectory = sys_get_temp_dir().'/filament-tenancy-tests-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->databaseDirectory);
        $app->useDatabasePath($this->databaseDirectory);
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'database.default' => 'central',
            'database.connections.central' => $this->centralConnection(),
            'filament-tenancy.central_connection' => 'central',
            'filament-tenancy.user_model' => User::class,
            'filament-tenancy.queue_connection' => 'sync',
            'filament-tenancy.migration_path' => __DIR__.'/Fixtures/migrations',
            'auth.providers.users.model' => User::class,
        ]);
    }

    protected function centralConnection(): array
    {
        $driver = getenv('TENANCY_TEST_DB') ?: 'sqlite';
        if ($driver === 'sqlite') {
            return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
        }

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new \RuntimeException('TENANCY_TEST_DB must be sqlite, mysql, or pgsql.');
        }

        return [
            'driver' => $driver,
            'host' => getenv('TENANCY_TEST_HOST') ?: '127.0.0.1',
            'port' => getenv('TENANCY_TEST_PORT') ?: ($driver === 'mysql' ? 3306 : 5432),
            'database' => getenv('TENANCY_TEST_DATABASE') ?: 'tenancy_test',
            'username' => getenv('TENANCY_TEST_USERNAME') ?: ($driver === 'mysql' ? 'root' : 'postgres'),
            'password' => getenv('TENANCY_TEST_PASSWORD') ?: 'tenancy-test',
            'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // External test databases must be disposable: reset the central schema per test.
        if (getenv('TENANCY_TEST_DB') && getenv('TENANCY_TEST_DB') !== 'sqlite') {
            Schema::dropAllTables();
        }
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            tenancy()->end();
            if (getenv('TENANCY_TEST_DB') && getenv('TENANCY_TEST_DB') !== 'sqlite') {
                foreach (TenantModel::get()::all() as $tenant) {
                    $database = $tenant->database();
                    if ($database->manager()->databaseExists($database->getName())) {
                        $database->manager()->deleteDatabase($tenant);
                    }
                }
            }
        }
        File::deleteDirectory($this->databaseDirectory);
        parent::tearDown();
    }

    protected function user(string $email = 'owner@example.test'): User
    {
        return User::create(['name' => 'Owner', 'email' => $email]);
    }
}
