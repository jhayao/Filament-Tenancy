<?php

namespace Liern\FilamentTenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Support\TenantModel;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        $scopeResources = config('filament-tenancy.scope_resources_to_tenant');
        $strategy = config('filament-tenancy.database_strategy', 'dedicated');

        $shouldScope = $scopeResources ?? ($strategy === 'shared');

        if ($shouldScope) {
            static::addGlobalScope('workspace', function (Builder $builder) {
                if (tenancy()->initialized) {
                    $builder->where(
                        $builder->getModel()->getTable().'.'.static::getWorkspaceKeyName(),
                        tenant()->getTenantKey()
                    );
                }
            });

            static::creating(function (Model $model) {
                if (tenancy()->initialized && ! $model->getAttribute(static::getWorkspaceKeyName())) {
                    $model->setAttribute(static::getWorkspaceKeyName(), tenant()->getTenantKey());
                }
            });
        }
    }

    public static function getWorkspaceKeyName(): string
    {
        return 'workspace_id'; // Can be customized per model
    }

    public function workspace()
    {
        return $this->belongsTo(TenantModel::get(), static::getWorkspaceKeyName());
    }
}
