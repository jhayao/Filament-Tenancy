<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Bus;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\TenancyPlugin;
use Liern\FilamentTenancy\TenancyServiceProvider;
use Liern\FilamentTenancy\Tests\Fixtures\AuditTenantAccess;
use Liern\FilamentTenancy\Tests\Fixtures\RegisterOrganization;
use Livewire\Livewire;
use LogicException;

class ConfigurationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'filament-tenancy.route_prefix' => 'teams',
            'filament-tenancy.registration_page' => RegisterOrganization::class,
            'filament-tenancy.menu.searchable' => false,
            'filament-tenancy.extra_tenant_middleware' => [AuditTenantAccess::class],
        ]);
    }

    public function test_custom_registration_and_prefix_have_working_redirects(): void
    {
        Bus::fake();
        $this->actingAs($this->user());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        $this->assertSame(RegisterOrganization::class, Filament::getPanel('admin')->getTenantRegistrationPage());
        $this->get(Filament::getPanel('admin')->getTenantRegistrationUrl())->assertOk();
        Livewire::test(RegisterOrganization::class)
            ->fillForm(['name' => 'Acme', 'slug' => 'acme'])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/teams/acme/workspace-setup');
        $this->get('/admin/teams/acme')->assertRedirect('/admin/teams/acme/workspace-setup');
        $this->get('/admin/teams/acme/workspace-setup')->assertOk();
        $this->get('/admin/workspaces/acme')->assertNotFound();
    }

    public function test_fluent_overrides_are_panel_local_and_preserve_explicit_null(): void
    {
        $first = Panel::make()->id('first')->plugin(TenancyPlugin::make()
            ->routePrefix('organizations')
            ->withTenantRegistration(null)
            ->withTenantMenu(false)
            ->withTenantSwitcher(false)
            ->searchableTenantMenu()
            ->extraTenantMiddleware([]));
        $second = Panel::make()->id('second')->plugin(TenancyPlugin::make());
        $third = Panel::make()->id('third')->plugin(TenancyPlugin::make()->withTenantRegistration());

        $this->assertSame('organizations', $first->getTenantRoutePrefix());
        $this->assertFalse($first->hasTenantRegistration());
        $this->assertFalse($first->hasTenantMenu());
        $this->assertFalse($first->hasTenantSwitcher());
        $this->assertTrue($first->isTenantMenuSearchable());
        $this->assertNotContains(AuditTenantAccess::class, $first->getTenantMiddleware());
        $this->assertSame('teams', $second->getTenantRoutePrefix());
        $this->assertSame(RegisterOrganization::class, $second->getTenantRegistrationPage());
        $this->assertFalse($second->isTenantMenuSearchable());
        $this->assertSame(RegisterWorkspace::class, $third->getTenantRegistrationPage());
        $middleware = $second->getTenantMiddleware();
        $this->assertLessThan(array_search(AuditTenantAccess::class, $middleware), array_search(InitializeWorkspace::class, $middleware));
        $this->assertSame('teams', config('filament-tenancy.route_prefix'));
    }

    public function test_extra_middleware_runs_on_http_and_livewire_after_initialization(): void
    {
        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        AuditTenantAccess::$tenantIds = [];
        $this->actingAs($owner);
        $response = $this->get('/admin/teams/acme/notes')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES))
            ->first(fn ($value) => str_contains(json_decode($value, true)['memo']['name'], 'ManageNotes'));
        $this->assertNotNull($snapshot);
        $this->assertSame([$workspace->id], AuditTenantAccess::$tenantIds);
        Livewire::flushState();
        $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertOk();
        $this->assertSame([$workspace->id, $workspace->id], AuditTenantAccess::$tenantIds);

        $workspace->users()->detach($owner);
        $this->get('/admin/teams/acme/notes')->assertNotFound();
        $this->assertCount(2, AuditTenantAccess::$tenantIds);
    }

    public function test_invalid_model_configuration_fails_early(): void
    {
        config(['filament-tenancy.tenant_model' => \stdClass::class]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('filament-tenancy.tenant_model');
        try {
            (new TenancyServiceProvider($this->app))->boot();
        } finally {
            config(['filament-tenancy.tenant_model' => Tenant::class]);
        }
    }

    public function test_partial_menu_configuration_keeps_other_defaults(): void
    {
        config(['filament-tenancy.menu' => ['enabled' => false]]);
        (new TenancyServiceProvider($this->app))->register();
        $panel = Panel::make()->id('partial')->plugin(TenancyPlugin::make());
        $this->assertFalse($panel->hasTenantMenu());
        $this->assertTrue($panel->hasTenantSwitcher());
        $this->assertTrue($panel->isTenantMenuSearchable());
    }

    public function test_default_model_remains_supported(): void
    {
        $this->assertSame(Tenant::class, TenantModel::get());
    }

    public function test_invalid_registration_page_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        Panel::make()->plugin(TenancyPlugin::make()->withTenantRegistration(\stdClass::class));
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        Panel::make()->plugin(TenancyPlugin::make()->routePrefix('../admin'));
    }
}
