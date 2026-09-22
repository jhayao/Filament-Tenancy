<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class InvalidRoleException extends TeamsException
{
    public function __construct(string $reason = 'invalid_role')
    {
        parent::__construct($reason);
    }
}
