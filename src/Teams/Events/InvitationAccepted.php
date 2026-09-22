<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Teams\Invitation;

class InvitationAccepted
{
    public function __construct(public Model $team, public Model $user, public Invitation $invitation) {}
}
