<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;

class MemberRoleChanged
{
    public function __construct(public Model $team, public Model $user, public string $oldRole, public string $role) {}
}
