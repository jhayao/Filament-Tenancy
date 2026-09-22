<?php

namespace Liern\FilamentTenancy\Teams\Auth;

use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

trait LocksInvitationEmail
{
    public function mount(): void
    {
        parent::mount();
        if ($email = app(InvitationContext::class)->email()) {
            $this->data['email'] = $email;
        }
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->readOnly(fn () => app(InvitationContext::class)->email() !== null);
    }

    protected function enforceInvitationEmail(array $data): array
    {
        if (($email = app(InvitationContext::class)->email()) !== null) {
            if (strtolower((string) ($data['email'] ?? '')) !== $email) {
                throw ValidationException::withMessages(['data.email' => __('filament-tenancy::teams.errors.email_mismatch')]);
            }
            $data['email'] = $email;
        }

        return $data;
    }
}
