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
        Filament::setTenant(null, isQuiet: true);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
            Filament::setTenant(null, isQuiet: true);
        }
    }
}
