<?php

namespace Liern\FilamentTenancy\Teams\Auth;

class Login extends \Filament\Auth\Pages\Login
{
    use LocksInvitationEmail;

    protected function getCredentialsFromFormData(array $data): array
    {
        return parent::getCredentialsFromFormData($this->enforceInvitationEmail($data));
    }
}
