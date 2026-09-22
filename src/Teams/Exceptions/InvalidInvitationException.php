<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class InvalidInvitationException extends TeamsException
{
    public function __construct(string $reason = 'invalid_invitation')
    {
        parent::__construct($reason);
    }
}
