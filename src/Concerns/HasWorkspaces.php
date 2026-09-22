<?php

namespace Liern\FilamentTenancy\Concerns;

use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\Teams\Teams;

trait HasWorkspaces
{
    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }

    public function workspaces(): BelongsToMany
    {
        if (config('teams.enabled')) {
            return app(Teams::class)->forUser($this);
        }

        return $this->belongsToMany(TenantModel::get(), 'workspace_user', 'user_id', 'workspace_id')->withPivot('is_owner')->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->workspaces()->get();
    }

    public function hasTeamPermission(Model $team, string $permission): bool
    {
        return app(Teams::class)->hasTeamPermission($this, $team, $permission);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return is_a($tenant, TenantModel::get()) && $this->workspaces()->whereKey($tenant->getKey())->exists();
    }
}
