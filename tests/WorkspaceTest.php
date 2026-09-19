<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Livewire\Livewire;

class WorkspaceTest extends TestCase
{
    public function test_onboarding_creates_a_database_and_owner_membership(): void
    {
        $user = $this->user();
        $tenant = app(CreateWorkspace::class)->create($user, ['name' => 'Acme', 'slug' => 'acme', 'status' => 'ready', 'tenancy_db_name' => 'injected']);

        $this->assertSame(ProvisioningStatus::Ready, $tenant->fresh()->status);
        $this->assertTrue($user->canAccessTenant($tenant));
        $this->assertTrue((bool) $tenant->users()->first()->pivot->is_owner);
        $this->assertSame($user->id, $tenant->load('users')->users->sole()->id);
        $this->assertSame(1, Tenant::whereHas('users', fn ($query) => $query->where('email', $user->email))->count());
        $this->assertNotSame('injected', $tenant->fresh()->database()->getName());
        $this->assertSame('central', DB::getDefaultConnection());
        $this->assertFalse(Schema::hasTable('notes'));
    }

    public function test_tenant_databases_are_isolated_and_retry_does_not_erase_data(): void
    {
        $owner = $this->user();
        $a = app(CreateWorkspace::class)->create($owner, ['name' => 'A', 'slug' => 'a']);
        $b = app(CreateWorkspace::class)->create($owner, ['name' => 'B', 'slug' => 'b']);

        $a->run(fn () => DB::table('notes')->insert(['body' => 'Only A']));
        $b->run(fn () => $this->assertSame(0, DB::table('notes')->count()));
        $a->update(['status' => ProvisioningStatus::Failed]);
        (new ProvisionWorkspace($a->id))->handle();
        $a->run(fn () => $this->assertSame('Only A', DB::table('notes')->value('body')));
        $this->assertSame('central', DB::getDefaultConnection());
    }

    public function test_failure_restores_the_central_connection_and_can_be_retried(): void
    {
        Bus::fake();
        $tenant = app(CreateWorkspace::class)->create($this->user(), ['name' => 'Broken', 'slug' => 'broken']);
        config(['filament-tenancy.migration_path' => '/missing-tenant-migrations']);
        try {
            (new ProvisionWorkspace($tenant->id))->handle();
            $this->fail('Expected provisioning failure.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('migration directory', $exception->getMessage());
        }
        $this->assertSame(ProvisioningStatus::Failed, $tenant->fresh()->status);
        $this->assertSame('central', DB::getDefaultConnection());
        $this->assertFalse(tenancy()->initialized);
        $this->artisan('workspaces:retry', ['tenant' => $tenant->id])->assertSuccessful();
        Bus::assertDispatched(ProvisionWorkspace::class);
    }

    public function test_duplicate_slugs_are_rejected(): void
    {
        Bus::fake();
        $owner = $this->user();
        app(CreateWorkspace::class)->create($owner, ['name' => 'One', 'slug' => 'same']);
        $this->expectException(ValidationException::class);
        app(CreateWorkspace::class)->create($owner, ['name' => 'Two', 'slug' => 'same']);
    }

    public function test_late_failure_from_duplicate_job_does_not_disable_ready_workspace(): void
    {
        $tenant = app(CreateWorkspace::class)->create($this->user(), ['name' => 'Acme', 'slug' => 'acme']);
        (new ProvisionWorkspace($tenant->id))->failed(new \RuntimeException('Duplicate job exhausted attempts'));
        $this->assertSame(ProvisioningStatus::Ready, $tenant->fresh()->status);
        $this->artisan('workspaces:retry', ['tenant' => $tenant->id])->assertFailed();
    }

    public function test_registration_form_creates_a_pending_workspace_and_redirects(): void
    {
        Bus::fake();
        $this->actingAs($this->user());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        Livewire::test(RegisterWorkspace::class)
            ->fillForm(['name' => 'Acme', 'slug' => 'acme'])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/workspaces/acme/workspace-setup');

        $this->assertSame(ProvisioningStatus::Pending, Tenant::first()->status);
        Bus::assertDispatched(ProvisionWorkspace::class);
    }

    public function test_routes_deny_non_members_and_redirect_pending_members(): void
    {
        Bus::fake();
        $owner = $this->user();
        app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->actingAs($this->user('stranger@example.test'));
        $this->get('/admin/workspaces/acme')->assertNotFound();
        $this->get('/admin/workspaces/acme/workspace-setup')->assertNotFound();
        $this->actingAs($owner);
        $this->get('/admin/workspaces/acme')->assertRedirect('/admin/workspaces/acme/workspace-setup');
        $this->get('/admin/workspaces/acme/workspace-setup')->assertOk()->assertSee('Waiting for setup');
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_ready_workspace_renders_and_cleans_up_http_context(): void
    {
        $owner = $this->user();
        app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->actingAs($owner);
        $this->get('/admin/workspaces/acme')->assertOk();
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('central', DB::getDefaultConnection());
    }

    public function test_resource_http_and_livewire_requests_keep_tenant_isolation(): void
    {
        $owner = $this->user();
        $a = app(CreateWorkspace::class)->create($owner, ['name' => 'A', 'slug' => 'a']);
        $b = app(CreateWorkspace::class)->create($owner, ['name' => 'B', 'slug' => 'b']);
        $a->run(fn () => DB::table('notes')->insert(['body' => 'Only workspace A']));
        $b->run(fn () => DB::table('notes')->insert(['body' => 'Only workspace B']));
        $this->actingAs($owner);

        $response = $this->get('/admin/workspaces/a/notes')->assertOk()->assertSee('Only workspace A')->assertDontSee('Only workspace B');
        $snapshot = $this->snapshot($response->getContent(), 'ManageNotes');
        $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]]];

        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => 'true'])->assertOk()->assertSee('Only workspace A')->assertDontSee('Only workspace B');
        $this->assertSame('central', DB::getDefaultConnection());

        $responseB = $this->get('/admin/workspaces/b/notes')->assertOk()->assertSee('Only workspace B')->assertDontSee('Only workspace A');
        $mixedPayload = $payload;
        $mixedPayload['components'][] = [...$payload['components'][0], 'snapshot' => $this->snapshot($responseB->getContent(), 'ManageNotes')];
        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), $mixedPayload, ['X-Livewire' => 'true'])->assertStatus(409);
        $this->assertFalse(tenancy()->initialized);

        $owner->workspaces()->detach($a);
        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => 'true'])->assertNotFound();
    }

    public function test_setup_polling_redirects_only_when_ready(): void
    {
        Bus::fake();
        $owner = $this->user();
        $tenant = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->actingAs($owner);
        $response = $this->get('/admin/workspaces/acme/workspace-setup')->assertOk();
        $payload = ['components' => [[
            'snapshot' => $this->snapshot($response->getContent(), 'Provisioning'),
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'checkStatus', 'params' => []]],
        ]]];
        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => 'true'])
            ->assertOk()->assertJsonMissingPath('components.0.effects.redirect');
        (new ProvisionWorkspace($tenant->id))->handle();
        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), $payload, ['X-Livewire' => 'true'])
            ->assertOk()->assertJsonPath('components.0.effects.redirect', 'http://localhost/admin/workspaces/acme');
        $this->assertSame('central', DB::getDefaultConnection());
    }

    public function test_guest_is_sent_to_login_and_unknown_workspaces_are_not_found(): void
    {
        $this->get('/admin/workspaces/unknown')->assertRedirect('/admin/login');
        $this->actingAs($this->user());
        $this->get('/admin/workspaces/unknown')->assertNotFound();
    }

    public function test_invalid_slugs_do_not_create_workspace_or_membership(): void
    {
        Bus::fake();
        $owner = $this->user();
        foreach (['../central', 'UPPER', 'two--hyphens', 'a/b', 'a.b'] as $slug) {
            try {
                app(CreateWorkspace::class)->create($owner, ['name' => 'Invalid', 'slug' => $slug]);
                $this->fail('Expected invalid slug rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('slug', $exception->errors());
            }
        }
        $this->assertSame(0, Tenant::count());
        $this->assertSame(0, $owner->workspaces()->count());
        Bus::assertNothingDispatched();
    }

    private function snapshot(string $html, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES))
            ->first(fn ($value) => str_contains(json_decode($value, true)['memo']['name'], $component));
        $this->assertNotNull($snapshot);

        return $snapshot;
    }
}
