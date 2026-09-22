<?php

namespace Liern\FilamentTenancy\Teams\Auth;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsException;
use Liern\FilamentTenancy\Teams\Invitations;

class InvitationContext
{
    public function email(): ?string
    {
        $token = session('teams.invitation');
        if (! $token) {
            return null;
        }
        $invitation = app(Invitations::class)->find($token);
        if (! $invitation->isActive() || $invitation->panel_id !== Filament::getCurrentPanel()?->getId()) {
            return null;
        }

        return $invitation->email;
    }

    public function login(Login $event): void
    {
        if (! config('teams.enabled') || ! $event->user instanceof Model) {
            return;
        }
        $invitations = app(Invitations::class);
        if (app()->bound('session') && ($token = session('teams.invitation'))) {
            try {
                $invitation = $invitations->find($token);
                if ($invitation->guard === $event->guard && $invitation->panel_id === Filament::getCurrentPanel()?->getId()) {
                    $invitations->accept($event->user, $token);
                    session()->forget('teams.invitation');
                    session()->put('url.intended', $invitation->url());
                }
            } catch (TeamsException $exception) {
                session()->flash('teams.error', $exception->getMessage());
            }
        }
        $invitations->acceptPending($event->user, $event->guard);
    }
}
