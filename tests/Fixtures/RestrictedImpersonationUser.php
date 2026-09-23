<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

class RestrictedImpersonationUser extends User
{
    protected $table = 'users';

    public function canImpersonate(): bool
    {
        return false;
    }

    public function canBeImpersonated(): bool
    {
        return false;
    }
}
