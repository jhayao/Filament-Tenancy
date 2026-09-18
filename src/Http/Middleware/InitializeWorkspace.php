<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\Provisioning;

class InitializeWorkspace
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();
        abort_unless($tenant instanceof Tenant && $user instanceof HasTenants && $user->canAccessTenant($tenant), 404);

        // Runs again for Livewire updates: never trust a stale status or membership.
        $tenant->refresh();
        if ($request->routeIs(Provisioning::getRouteName())) {
            tenancy()->end();

            return $next($request);
        }

        if ($tenant->status !== ProvisioningStatus::Ready) {
            return redirect(Provisioning::getUrl(tenant: $tenant));
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
