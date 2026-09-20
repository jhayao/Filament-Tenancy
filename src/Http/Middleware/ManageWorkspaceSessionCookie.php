<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ManageWorkspaceSessionCookie
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('filament-tenancy.manage_session_cookie', false)
            || config('filament-tenancy.identification') !== 'subdomain') {
            return $next($request);
        }

        $originalDomain = config('session.domain');
        $centralDomain = strtolower(ltrim((string) config('filament-tenancy.central_domain'), '.'));
        $host = strtolower(rtrim($request->getHost(), '.'));
        $sharedDomain = $centralDomain !== '' && ($host === $centralDomain || str_ends_with($host, '.'.$centralDomain));

        config(['session.domain' => $sharedDomain ? '.'.$centralDomain : null]);

        try {
            return $next($request);
        } finally {
            config(['session.domain' => $originalDomain]);
        }
    }
}
