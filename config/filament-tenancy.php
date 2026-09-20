<?php

use App\Models\User;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Pages\WorkspaceDomains;
use Liern\FilamentTenancy\Pages\WorkspaceProfile;

return [
    'tenant_model' => Tenant::class,
    'identification' => 'path', // 'subdomain' or 'path'
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'database_strategy' => 'dedicated', // 'dedicated' or 'shared'
    'database_name_prefix' => env('TENANCY_DATABASE_NAME_PREFIX', 'tenant_'),
    'route_prefix' => 'workspaces',
    'registration_page' => RegisterWorkspace::class, // null disables self-service registration.
    'menu' => ['enabled' => true, 'switcher_enabled' => true, 'searchable' => true],
    'profile' => [
        'enabled' => true,
        'page' => WorkspaceProfile::class,
        'logo_disk' => env('TENANCY_PROFILE_LOGO_DISK', 'public'),
        'logo_directory' => env('TENANCY_PROFILE_LOGO_DIRECTORY', 'workspaces'),
    ],
    'custom_domains' => [
        'enabled' => env('TENANCY_CUSTOM_DOMAINS_ENABLED', false),
        'page' => WorkspaceDomains::class,
        'verification_prefix' => '_lona-verify.',
        'verification_attempts' => 6,
        'verification_decay_seconds' => 300,
        'reserved_suffixes' => [
            'laravel.cloud',
            'cloudflare.com',
            'workers.dev',
            'pages.dev',
            'vercel.app',
            'netlify.app',
        ],
    ],
    'billing' => ['enabled' => false, 'provider' => null],
    'extra_tenant_middleware' => [],
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'database_pool' => [], // Array of connection names to round-robin for new tenants (dedicated strategy)
    'user_model' => User::class,
    'queue_connection' => env('TENANCY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'queue' => 'default',
    'seeder' => null,
    'migration_path' => database_path('migrations/tenant'),
    'scope_resources_to_tenant' => null,
];
