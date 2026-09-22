<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Spatie\Permission\Models\Permission;

class CentralPermission extends Permission
{
    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }
}
