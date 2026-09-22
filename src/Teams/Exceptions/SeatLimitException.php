<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class SeatLimitException extends TeamsException
{
    public function __construct(string $reason = 'full')
    {
        parent::__construct($reason);
    }
}
