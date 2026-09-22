<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Teams\Shield;

class SetTeamPermissions
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! app(Shield::class)->enabled()) {
            return $next($request);
        }
        app(Shield::class)->validate();
        $team = Filament::getTenant();
        $user = Filament::auth()->user();
        setPermissionsTeamId($team?->getKey());
        $user?->unsetRelation('roles')->unsetRelation('permissions');
        try {
            return $next($request);
        } finally {
            setPermissionsTeamId(null);
            $user?->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
