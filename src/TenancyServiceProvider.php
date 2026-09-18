<?php

namespace Liern\FilamentTenancy;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Liern\FilamentTenancy\Commands\RetryProvisioning;
use Liern\FilamentTenancy\Http\Middleware\ResetWorkspaceContext;
use Liern\FilamentTenancy\Models\Tenant;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-tenancy.php', 'filament-tenancy');
    }

    public function boot(): void
    {
        config([
            'tenancy.tenant_model' => Tenant::class,
            'tenancy.database.central_connection' => config('filament-tenancy.central_connection'),
            'tenancy.bootstrappers' => [DatabaseTenancyBootstrapper::class, QueueTenancyBootstrapper::class],
        ]);

        // Central infrastructure must never follow the tenant's default connection.
        foreach (['session.connection', 'queue.connections.database.connection', 'cache.stores.database.connection', 'cache.stores.database.lock_connection'] as $key) {
            if (config($key) === null) {
                config([$key => config('filament-tenancy.central_connection')]);
            }
        }

        Event::listen(Events\TenancyInitialized::class, Listeners\BootstrapTenancy::class);
        Event::listen(Events\TenancyEnded::class, Listeners\RevertToCentralContext::class);

        // Outer HTTP middleware also surrounds Livewire's synthetic middleware pass.
        $this->app->make(Kernel::class)->pushMiddleware(ResetWorkspaceContext::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-tenancy');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-tenancy');

        if ($this->app->runningInConsole()) {
            $this->commands([RetryProvisioning::class]);
            $this->publishes([__DIR__.'/../config/filament-tenancy.php' => config_path('filament-tenancy.php')], 'filament-tenancy-config');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'filament-tenancy-migrations');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/filament-tenancy')], 'filament-tenancy-views');
        }
    }
}
