<?php

namespace Liern\FilamentTenancy;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use LogicException;

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
        // Never mutate Resource's inherited static flag: it can affect other panels.
        foreach ($panel->getResources() as $resource) {
            if ($resource::isScopedToTenant()) {
                throw new LogicException("{$resource} must extend Liern\\FilamentTenancy\\Resources\\TenantResource or declare protected static bool \$isScopedToTenant = false.");
            }
        }
    }
}
