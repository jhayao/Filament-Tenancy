<?php

namespace Liern\FilamentTenancy\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Support\TenantModel;

class InitializeWorkspace
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();
        abort_unless(is_a($tenant, TenantModel::get()) && $user instanceof HasTenants && $user->canAccessTenant($tenant), 404);

        if (config('filament-tenancy.identification') === 'subdomain' && config('filament-tenancy.custom_domains.enabled')) {
            $hostTenant = app(TenantModel::get())->resolveRouteBinding($request->getHost(), 'slug');
            abort_unless($hostTenant !== null && $hostTenant->getKey() === $tenant->getKey(), 404);
        }

        // A batched Livewire request must not mix snapshots from different workspaces.
        $requestTenant = request()->attributes->get('liern.workspace');
        abort_if($requestTenant !== null && $requestTenant !== $tenant->getKey(), 409);
        request()->attributes->set('liern.workspace', $tenant->getKey());

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
