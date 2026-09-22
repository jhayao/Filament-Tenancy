<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Teams\Teams;

class TeamCan
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $team = Filament::getTenant();
        $user = Filament::auth()->user();
        abort_unless($team && $user && app(Teams::class)->hasTeamPermission($user, $team, $permission), 403);

        return $next($request);
    }
}
