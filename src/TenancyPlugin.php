<?php

namespace Liern\FilamentTenancy;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;

class TenancyPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'liern-tenancy';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantRoutePrefix('workspaces')
            ->tenantRegistration(RegisterWorkspace::class)
            ->searchableTenantMenu()
            ->pages([Provisioning::class])
            ->tenantMiddleware([InitializeWorkspace::class], isPersistent: true);
    }

    public function boot(Panel $panel): void
    {
        // Dedicated databases have no tenant_id relationship to scope.
        foreach ($panel->getResources() as $resource) {
            $resource::scopeToTenant(false);
        }
    }
}
