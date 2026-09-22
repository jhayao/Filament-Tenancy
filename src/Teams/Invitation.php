<?php

namespace Liern\FilamentTenancy\Teams;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'team_invitations';

    protected $guarded = [];

    protected $hidden = ['token', 'token_hash'];

    protected $casts = [
        'token' => 'encrypted', 'expires_at' => 'immutable_datetime',
        'last_sent_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime', 'uses' => 'integer', 'use_limit' => 'integer',
    ];

    public function getConnectionName()
    {
        return config('teams.connection') ?? config('filament-tenancy.central_connection');
    }

    public function isActive(): bool
    {
        return ! $this->revoked_at && ! $this->accepted_at && $this->expires_at->isFuture() && $this->uses < $this->use_limit;
    }

    public function url(): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $host = config('filament-tenancy.central_domain') ?: parse_url(config('app.url'), PHP_URL_HOST);
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$host.($port ? ':'.$port : '').route('teams.invitation', ['token' => $this->token], false);
    }
}
