<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class OwnershipException extends TeamsException
{
    public function __construct(string $reason = 'last_owner')
    {
        parent::__construct($reason);
    }
}
