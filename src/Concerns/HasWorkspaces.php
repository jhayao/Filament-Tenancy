<?php

namespace Liern\FilamentTenancy\Concerns;

use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liern\FilamentTenancy\Models\Tenant;

trait HasWorkspaces
{
    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'workspace_user', 'user_id', 'workspace_id')->withPivot('is_owner')->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->workspaces()->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Tenant && $this->workspaces()->whereKey($tenant->getKey())->exists();
    }
}
