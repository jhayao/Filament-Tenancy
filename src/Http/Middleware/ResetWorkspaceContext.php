<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class ResetWorkspaceContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        tenancy()->end();
        if (config('teams.shield.enabled') && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        Filament::setTenant(null, isQuiet: true);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
            if (config('teams.shield.enabled') && function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId(null);
                Filament::auth()->user()?->unsetRelation('roles')->unsetRelation('permissions');
            }
            Filament::setTenant(null, isQuiet: true);
        }
    }
}
