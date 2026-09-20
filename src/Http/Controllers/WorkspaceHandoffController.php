<?php

namespace Liern\FilamentTenancy\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use RuntimeException;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\Support\WorkspaceHandoffManager;

class WorkspaceHandoffController
{
    public function __invoke(Request $request, string $token)
    {
        abort_unless(config('filament-tenancy.custom_domains.enabled'), 404);

        try {
            $handoff = app(WorkspaceHandoffManager::class)->consume($token, $request);
        } catch (RuntimeException) {
            abort(404);
        }
        $panel = Filament::getPanel($handoff->panel_id);
        $user = app(config('filament-tenancy.user_model'))->newQuery()->find($handoff->user_id);
        $tenant = TenantModel::get()::find($handoff->workspace_id);

        abort_unless($panel !== null && $user !== null && $tenant !== null && $user->canAccessTenant($tenant), 404);

        abort_unless(auth()->guard($handoff->guard)->loginUsingId($user->getAuthIdentifier()), 404);
        Filament::setCurrentPanel($panel);
        Filament::setTenant($tenant, isQuiet: true);

        return redirect($panel->getUrl($tenant));
    }
}
