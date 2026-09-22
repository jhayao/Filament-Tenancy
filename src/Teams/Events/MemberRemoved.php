<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;

class MemberRemoved
{
    public function __construct(public Model $team, public Model $user) {}
}
