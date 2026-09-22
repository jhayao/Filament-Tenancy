<?php

namespace Liern\FilamentTenancy\Teams;

class DatabaseNotification extends \Illuminate\Notifications\DatabaseNotification
{
    public function getConnectionName()
    {
        return config('teams.connection') ?? config('filament-tenancy.central_connection');
    }
}
