<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class ExternalTeam extends Model
{
    protected $table = 'external_teams';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
