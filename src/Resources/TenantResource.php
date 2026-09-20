<?php

namespace Liern\FilamentTenancy\Resources;

use Filament\Resources\Resource;

abstract class TenantResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    public static function isScopedToTenant(): bool
    {
        return config('filament-tenancy.scope_resources_to_tenant')
            ?? config('filament-tenancy.database_strategy', 'dedicated') === 'shared';
    }
}
