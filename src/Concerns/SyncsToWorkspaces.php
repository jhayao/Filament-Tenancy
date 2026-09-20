<?php

namespace Liern\FilamentTenancy\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liern\FilamentTenancy\Support\TenantModel;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;

trait SyncsToWorkspaces
{
    use ResourceSyncing;

    public function tenants(): BelongsToMany
    {
        if (method_exists($this, 'workspaces')) {
            return $this->workspaces();
        }

        return $this->belongsToMany(TenantModel::get(), 'workspace_user', 'user_id', 'workspace_id')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    public function getGlobalIdentifierKeyName(): string
    {
        return $this->getKeyName();
    }

    public function getGlobalIdentifierKey(): mixed
    {
        return $this->getKey();
    }

    public function getCentralModelName(): string
    {
        foreach ((array) config('filament-tenancy.resource_syncing.pairs', []) as $central => $tenant) {
            if ($this instanceof $tenant) {
                return $central;
            }
        }

        return static::class;
    }

    public function getTenantModelName(): string
    {
        foreach ((array) config('filament-tenancy.resource_syncing.pairs', []) as $central => $tenant) {
            if ($this instanceof $central) {
                return $tenant;
            }
        }

        return static::class;
    }

    public function getSyncedAttributeNames(): array
    {
        if (method_exists($this, 'syncedAttributes')) {
            return array_values($this->syncedAttributes());
        }

        return property_exists($this, 'syncedAttributes')
            ? array_values($this->syncedAttributes)
            : [];
    }
}
