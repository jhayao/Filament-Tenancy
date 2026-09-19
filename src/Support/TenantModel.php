<?php

namespace Liern\FilamentTenancy\Support;

use Liern\FilamentTenancy\Models\Tenant;
use LogicException;
use ReflectionClass;

final class TenantModel
{
    /** @return class-string<Tenant> */
    public static function get(): string
    {
        $model = config('filament-tenancy.tenant_model', Tenant::class);

        if (! is_string($model) || ! is_a($model, Tenant::class, true) || (new ReflectionClass($model))->isAbstract()) {
            throw new LogicException('filament-tenancy.tenant_model must be a concrete subclass of '.Tenant::class.'.');
        }

        return $model;
    }
}
