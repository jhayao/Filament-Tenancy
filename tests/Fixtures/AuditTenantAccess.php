<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;

class AuditTenantAccess
{
    public static array $tenantIds = [];

    public function handle(Request $request, Closure $next): mixed
    {
        static::$tenantIds[] = tenant()?->getTenantKey();

        return $next($request);
    }
}
