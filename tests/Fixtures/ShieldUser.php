<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Spatie\Permission\Traits\HasRoles;

class ShieldUser extends User
{
    use HasRoles;

    protected $table = 'users';
}
