<?php

namespace Liern\FilamentTenancy;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Liern\FilamentTenancy\Http\Controllers\WorkspaceHandoffController;
use Liern\FilamentTenancy\Commands\ListWorkspaceDomains;
use Liern\FilamentTenancy\Commands\RemoveWorkspaceDomain;
use Liern\FilamentTenancy\Commands\PruneWorkspaceHandoffs;
use Liern\FilamentTenancy\Commands\RetryProvisioning;
use Liern\FilamentTenancy\Commands\VerifyWorkspaceDomain;
use Liern\FilamentTenancy\Http\Middleware\ResetWorkspaceContext;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Support\TenantModel;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/filament-tenancy.php', 'filament-tenancy');
    }

    public function boot(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing(
            fn ($tenant): string => config('filament-tenancy.database_name_prefix', 'tenant_').$tenant->getAttribute('slug')
        );

        config([
            'tenancy.tenant_model' => TenantModel::get(),
            'tenancy.domain_model' => WorkspaceDomain::class,
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

        Route::middleware('web')
            ->get('/lona-tenancy/handoff/{token}', WorkspaceHandoffController::class)
            ->name('lona-tenancy.handoff');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListWorkspaceDomains::class,
                RemoveWorkspaceDomain::class,
                PruneWorkspaceHandoffs::class,
                RetryProvisioning::class,
                VerifyWorkspaceDomain::class,
            ]);

            $this->app->booted(function (): void {
                if ($this->app->bound(Schedule::class)) {
                    $this->app->make(Schedule::class)->command(PruneWorkspaceHandoffs::class)->hourly();
                }
            });
            $this->publishes([__DIR__.'/../config/filament-tenancy.php' => config_path('filament-tenancy.php')], 'filament-tenancy-config');
            $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'filament-tenancy-migrations');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/filament-tenancy')], 'filament-tenancy-views');
        }
    }
}
