<?php

namespace Liern\FilamentTenancy\Teams\Auth;

class Register extends \Filament\Auth\Pages\Register
{
    use LocksInvitationEmail;

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        return parent::mutateFormDataBeforeRegister($this->enforceInvitationEmail($data));
    }
}
