<?php

namespace Liern\FilamentTenancy\Teams;

use Illuminate\Mail\Mailable;

class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation) {}

    public function build(): static
    {
        return $this->subject(__('filament-tenancy::teams.invited'))
            ->view('filament-tenancy::teams.email', ['url' => $this->invitation->url()]);
    }
}
