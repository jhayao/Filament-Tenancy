<?php

namespace Liern\FilamentTenancy\Teams\Events;

use Illuminate\Database\Eloquent\Model;

class OwnershipTransferred
{
    public function __construct(public Model $team, public Model $previousOwner, public Model $owner) {}
}
