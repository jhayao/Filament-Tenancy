<?php

namespace Liern\FilamentTenancy\Teams;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsConfigurationException;
use Spatie\Permission\PermissionRegistrar;

class ShieldRoleSeeder
{
    /**
     * @return array{roles: int, roles_reused: int, permissions: int, permissions_reused: int}
     */
    public function seed(Model $team, ?string $guard = null): array
    {
        $this->assertEnabled();
        app(Shield::class)->validate();

        $guard ??= app(Teams::class)->guard();
        $this->assertTables();
        $roleClass = config('permission.models.role');
        $permissionClass = config('permission.models.permission');
        $teamKey = app(PermissionRegistrar::class)->teamsKey;
        $definitions = config('teams.shield.seeding.roles') ?? config('teams.roles', []);
        $permissionOverrides = config('teams.shield.seeding.permissions');

        if (! is_array($definitions)) {
            throw new TeamsConfigurationException('shield_role_definitions');
        }
        if ($permissionOverrides !== null && (! is_array($permissionOverrides) || array_filter($permissionOverrides, fn ($permissions) => ! is_array($permissions)) !== [])) {
            throw new TeamsConfigurationException('shield_permissions');
        }

        $createdRoles = 0;
        $reusedRoles = 0;
        $createdPermissions = 0;
        $reusedPermissions = 0;
        $reserved = array_unique([
            'owner',
            config('filament-shield.super_admin.name', 'super_admin'),
            ...config('teams.shield.excluded_roles', []),
        ]);

        foreach ($definitions as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                throw new TeamsConfigurationException('shield_role_definitions');
            }

            $roleName = $key;
            if (in_array($roleName, $reserved, true)) {
                throw new TeamsConfigurationException('shield_reserved_role');
            }
            $permissions = $permissionOverrides === null
                ? ($definition['permissions'] ?? [])
                : ($permissionOverrides[$key] ?? ($definition['permissions'] ?? []));

            if (! is_array($permissions) || array_filter($permissions, fn ($permission) => ! is_string($permission) || $permission === '') !== []) {
                throw new TeamsConfigurationException('shield_permissions');
            }

            $role = $roleClass::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
                $teamKey => $team->getKey(),
            ]);
            $createdRoles += (int) $role->wasRecentlyCreated;
            $reusedRoles += (int) ! $role->wasRecentlyCreated;

            foreach (array_unique($permissions) as $permissionName) {
                $permission = $permissionClass::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);
                $createdPermissions += (int) $permission->wasRecentlyCreated;
                $reusedPermissions += (int) ! $permission->wasRecentlyCreated;
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'roles' => $createdRoles,
            'roles_reused' => $reusedRoles,
            'permissions' => $createdPermissions,
            'permissions_reused' => $reusedPermissions,
        ];
    }

    public function enabled(): bool
    {
        return (bool) config('teams.shield.enabled') && (bool) config('teams.shield.seeding.enabled');
    }

    protected function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new TeamsConfigurationException('shield_seeding_disabled');
        }
    }

    protected function assertTables(): void
    {
        $connection = app(Teams::class)->connection()->getName();
        $tables = config('permission.table_names', []);

        foreach (['roles', 'permissions', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $table) {
            $name = is_array($tables) ? ($tables[$table] ?? null) : null;
            if (! is_string($name) || ! Schema::connection($connection)->hasTable($name)) {
                throw new TeamsConfigurationException('shield_tables');
            }
        }
    }
}
