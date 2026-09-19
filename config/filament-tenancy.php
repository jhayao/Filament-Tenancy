<?php

use App\Models\User;

return [
    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'user_model' => User::class,
    'queue_connection' => env('TENANCY_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'queue' => 'tenant-provisioning',
    'seeder' => null,
    'migration_path' => database_path('migrations/tenant'),
];
