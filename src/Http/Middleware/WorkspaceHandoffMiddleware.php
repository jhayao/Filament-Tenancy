<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Controllers\RedirectToTenantController;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Support\WorkspaceHandoffManager;

class WorkspaceHandoffMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('filament-tenancy.custom_domains.enabled')) {
            return $next($request);
        }

        $centralDomain = strtolower((string) config('filament-tenancy.central_domain'));
        $host = strtolower(rtrim($request->getHost(), '.'));
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($host === $centralDomain) {
            if ($request->filled('lona_workspace')) {
                $domain = app(WorkspaceHandoffManager::class)->domainForHost((string) $request->query('lona_workspace'));

                abort_unless($domain !== null, 404);
                $request->session()->put('lona_handoff_domain_id', $domain->getKey());

                return redirect($request->url());
            }

            if ($request->session()->has('lona_handoff_domain_id') && Filament::auth()->check() && ! $this->isAuthenticationChallenge($request, $panel)) {
                $domain = WorkspaceDomain::query()
                    ->whereKey($request->session()->get('lona_handoff_domain_id'))
                    ->whereNotNull('verified_at')
                    ->first();

                $request->session()->forget('lona_handoff_domain_id');

                if ($domain !== null && $domain->workspace()->exists()) {
                    $token = app(WorkspaceHandoffManager::class)->create($domain, Filament::auth()->user(), $panel, $request);

                    return redirect(app(WorkspaceHandoffManager::class)->url($token, $domain->domain, $request));
                }
            }

            if (Filament::auth()->check() && trim($request->path(), '/') === trim($panel->getPath(), '/')) {
                return app(RedirectToTenantController::class)();
            }

            return $next($request);
        }

        $domain = app(WorkspaceHandoffManager::class)->domainForHost($host);

        if ($domain === null || Filament::auth()->check()) {
            return $next($request);
        }

        $loginPath = trim($panel->getPath(), '/').'/'.trim($panel->getLoginRouteSlug(), '/');
        $appUrl = parse_url((string) config('app.url')) ?: [];
        $port = isset($appUrl['port']) && ! in_array($appUrl['port'], [80, 443], true) ? ':'.$appUrl['port'] : '';
        $basePath = trim((string) ($appUrl['path'] ?? ''), '/');
        $centralUrl = ($appUrl['scheme'] ?? $request->getScheme()).'://'.$centralDomain.$port
            .($basePath !== '' ? '/'.$basePath : '')
            .'/'.$loginPath;

        return redirect($centralUrl.'?lona_workspace='.urlencode($domain->domain));
    }

    protected function isAuthenticationChallenge(Request $request, \Filament\Panel $panel): bool
    {
        if ($panel->isMultiFactorAuthenticationRequired() && $request->routeIs($panel->generateRouteName('auth.multi-factor-authentication.*'))) {
            return true;
        }

        return $panel->isEmailVerificationRequired() && $request->routeIs($panel->generateRouteName('auth.email-verification.*'));
    }
}
