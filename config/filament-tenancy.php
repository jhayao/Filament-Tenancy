<?php

use App\Models\User;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;

return [
    'tenant_model' => Tenant::class,
    'route_prefix' => 'workspaces',
    'registration_page' => RegisterWorkspace::class, // null disables self-service registration.
    'menu' => ['enabled' => true, 'switcher_enabled' => true, 'searchable' => true],
    'extra_tenant_middleware' => [],
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'user_model' => User::class,
    'queue_connection' => env('TENANCY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'queue' => 'tenant-provisioning',
    'seeder' => null,
    'migration_path' => database_path('migrations/tenant'),
];
