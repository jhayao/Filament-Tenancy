<?php

namespace Liern\FilamentTenancy\Teams\Exceptions;

class TeamsConfigurationException extends TeamsException
{
    public function __construct(string $reason = 'configuration')
    {
        parent::__construct($reason);
    }
}
