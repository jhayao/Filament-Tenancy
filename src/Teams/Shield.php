<?php

namespace Liern\FilamentTenancy\Teams;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidRoleException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsConfigurationException;

class Shield
{
    public function enabled(): bool
    {
        return (bool) config('teams.shield.enabled');
    }

    public function validate(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $user = app(app(Teams::class)->userModel());
        if (! class_exists(FilamentShieldPlugin::class)
            || ! config('permission.teams') || ! method_exists($user, 'assignRole')) {
            throw new TeamsConfigurationException('shield_configuration');
        }
        foreach (['role', 'permission'] as $type) {
            $class = config('permission.models.'.$type);
            $model = $class ? new $class : null;
            if (! $model || $model->getConnectionName() !== app(Teams::class)->connection()->getName()) {
                throw new TeamsConfigurationException('shield_connection');
            }
        }
    }

    public function context(Model $team, Model $user, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($team->getKey());
        $user->unsetRelation('roles')->unsetRelation('permissions');
        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function roles(Model $team, string $guard): array
    {
        $this->validate();
        $class = config('permission.models.role');
        $key = config('permission.column_names.team_foreign_key', 'team_id');
        $excluded = array_unique(['owner', config('filament-shield.super_admin.name', 'super_admin'), ...config('teams.shield.excluded_roles', [])]);

        return $class::query()->where('guard_name', $guard)
            ->where(fn ($q) => $q->whereNull($key)->orWhere($key, $team->getKey()))
            ->whereNotIn('name', $excluded)->orderBy($key)->get()
            ->mapWithKeys(fn ($role) => [$role->name => $role->name])->all();
    }

    public function sync(Model $team, Model $user, ?string $role, ?string $previousId, string $guard): ?string
    {
        if (! $this->enabled()) {
            return $previousId;
        }
        $this->validate();

        return $this->context($team, $user, function () use ($team, $user, $role, $previousId, $guard) {
            $class = config('permission.models.role');
            $key = config('permission.column_names.team_foreign_key', 'team_id');
            $role = $role === 'owner' ? config('teams.shield.owner_role') : $role;
            $next = null;
            if ($role !== null) {
                if (! array_key_exists($role, $this->roles($team, $guard))) {
                    throw new InvalidRoleException;
                }
                $next = $class::query()->where('guard_name', $guard)->where('name', $role)
                    ->where(fn ($q) => $q->whereNull($key)->orWhere($key, $team->getKey()))
                    ->orderByRaw('CASE WHEN '.$class::query()->getQuery()->getGrammar()->wrap($key).' IS NULL THEN 1 ELSE 0 END')->firstOrFail();
            }
            if ($previousId && (string) $next?->getKey() !== $previousId) {
                // Restrict detach to this team even if another team uses the same role.
                $user->roles()->wherePivot($key, $team->getKey())->detach($previousId);
            }
            $managed = $next && ((string) $next->getKey() === $previousId || ! $user->roles()->whereKey($next->getKey())->exists());
            if ($next) {
                $user->assignRole($next);
            }
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $managed ? (string) $next->getKey() : null;
        });
    }
}
