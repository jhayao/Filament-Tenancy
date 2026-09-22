<?php

namespace Liern\FilamentTenancy\Teams\Contracts;

use Illuminate\Database\Eloquent\Model;

interface SeatLimitResolver
{
    public function limit(Model $team): ?int;
}
