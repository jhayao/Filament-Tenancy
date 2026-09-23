<?php

return [
    'enabled' => false,
    'model' => null, // Defaults to filament-tenancy.tenant_model.
    'user_model' => null,
    'connection' => null, // Defaults to the tenancy central connection.
    'pivot' => 'workspace_user',
    'team_key' => 'workspace_id',
    'user_key' => 'user_id',
    'owner_column' => 'is_owner',
    'external' => false,
    'roles' => [
        'manager' => ['name' => 'Manager', 'permissions' => ['members.invite', 'members.update', 'members.remove']],
        'member' => ['name' => 'Member', 'permissions' => []],
    ],
    'manager_roles' => ['manager'],
    'manager_assignable_roles' => ['member'],
    'default_role' => 'member',
    'seat_limit' => null,
    'seat_limit_resolver' => null,
    'expiry_days' => 7,
    'resend_cooldown' => 60,
    'retention_days' => 30,
    'auto_accept' => true,
    'personal_teams' => false,
    'personal_team_creator' => null,
    'shield' => [
        'enabled' => false,
        'excluded_roles' => [],
        'owner_role' => null,
        'seeding' => [
            'enabled' => false,
            'roles' => null, // Defaults to teams.roles; keys become workspace role names.
            'permissions' => null, // Optional role-keyed permission overrides.
        ],
    ],
];
