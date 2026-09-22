<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;

class MemberAdded
{
    public function __construct(public Model $team, public Model $user, public string $role) {}
}
