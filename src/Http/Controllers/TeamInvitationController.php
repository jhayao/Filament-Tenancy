<?php

namespace Liern\FilamentTenancy\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidInvitationException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsException;
use Liern\FilamentTenancy\Teams\Invitations;
use Liern\FilamentTenancy\Teams\Teams;

class TeamInvitationController
{
    public function __invoke(Request $request, string $token): mixed
    {
        abort_unless(config('teams.enabled'), 404);
        $centralHost = config('filament-tenancy.central_domain') ?: parse_url(config('app.url'), PHP_URL_HOST);
        abort_unless(strtolower($request->getHost()) === strtolower($centralHost), 404);
        try {
            $invitations = app(Invitations::class);
            $invitation = $invitations->find($token);
            $panel = Filament::getPanel($invitation->panel_id);
            abort_unless($panel->getAuthGuard() === $invitation->guard, 404);
            Filament::setCurrentPanel($panel);
            Filament::bootCurrentPanel();
            $user = $panel->auth()->user();
            if ($user instanceof FilamentUser) {
                abort_unless($user->canAccessPanel($panel), 403);
            }
            $team = app(Teams::class)->team($invitation->team_id);
            if ($user && app(Teams::class)->membership($team, $user)) {
                return redirect($panel->getUrl($team));
            }
            if (! $invitation->isActive()) {
                throw new InvalidInvitationException;
            }
            if (! $user) {
                session()->put('teams.invitation', $token);
                session()->put('url.intended', $invitation->url());

                return redirect($panel->getLoginUrl());
            }
            if ($request->isMethod('post')) {
                $team = $invitations->accept($user, $token);
                session()->forget('teams.invitation');

                return redirect($panel->getUrl($team));
            }

            return response()->view('filament-tenancy::teams.accept', ['invitation' => $invitation, 'team' => $team]);
        } catch (TeamsException $exception) {
            return response()->view('filament-tenancy::teams.error', ['message' => $exception->getMessage()], 422);
        }
    }
}
