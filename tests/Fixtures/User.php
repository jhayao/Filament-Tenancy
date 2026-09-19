<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Liern\FilamentTenancy\Concerns\HasWorkspaces;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasWorkspaces;

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
