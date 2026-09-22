<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class TeamAuthorizationException extends TeamsException
{
    public function __construct(string $reason = 'forbidden')
    {
        parent::__construct($reason);
    }
}
