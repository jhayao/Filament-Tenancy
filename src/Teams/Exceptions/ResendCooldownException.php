<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class ResendCooldownException extends TeamsException
{
    public function __construct(string $reason = 'cooldown')
    {
        parent::__construct($reason);
    }
}
