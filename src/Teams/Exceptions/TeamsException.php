<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class TeamsException extends \RuntimeException
{
    public function __construct(public readonly string $reason = 'invalid_invitation')
    {
        parent::__construct(__('filament-tenancy::teams.errors.'.$reason));
    }
}
