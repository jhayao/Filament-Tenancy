<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Spatie\Permission\Models\Role;

class CentralRole extends Role
{
    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }
}
