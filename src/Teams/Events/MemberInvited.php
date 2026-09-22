<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Teams\Invitation;

class MemberInvited
{
    public function __construct(public Model $team, public Invitation $invitation) {}
}
