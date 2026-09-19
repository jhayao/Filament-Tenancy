<?php

use App\Models\User;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Pages\WorkspaceProfile;

return [
    'tenant_model' => Tenant::class,
    'identification' => 'path', // 'subdomain' or 'path'
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'database_strategy' => 'dedicated', // 'dedicated' or 'shared'
    'route_prefix' => 'workspaces',
    'registration_page' => RegisterWorkspace::class, // null disables self-service registration.
    'menu' => ['enabled' => true, 'switcher_enabled' => true, 'searchable' => true],
    'profile' => ['enabled' => false, 'page' => WorkspaceProfile::class],
    'billing' => ['enabled' => false, 'provider' => null],
    'extra_tenant_middleware' => [],
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'database_pool' => [], // Array of connection names to round-robin for new tenants (dedicated strategy)
    'user_model' => User::class,
    'queue_connection' => env('TENANCY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'queue' => 'tenant-provisioning',
    'seeder' => null,
    'migration_path' => database_path('migrations/tenant'),
    'scope_resources_to_tenant' => null,
];
