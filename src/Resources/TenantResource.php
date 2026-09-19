<?php

namespace Liern\FilamentTenancy\Resources;

use Filament\Resources\Resource;

abstract class TenantResource extends Resource
{
    protected static bool $isScopedToTenant = false;
}
