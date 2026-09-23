<?php

namespace Liern\FilamentTenancy\Teams;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Liern\FilamentTenancy\Commands\PruneTeamInvitations;
use Liern\FilamentTenancy\Commands\SeedShieldRoles;
use Liern\FilamentTenancy\Http\Controllers\TeamInvitationController;
use Liern\FilamentTenancy\Http\Middleware\TeamCan;
use Liern\FilamentTenancy\Teams\Auth\InvitationContext;

class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/teams.php', 'teams');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'filament-tenancy');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'filament-tenancy');
        Route::aliasMiddleware('team.can', TeamCan::class);
        Route::middleware(['web', 'throttle:60,1'])->match(['GET', 'POST'], '/team-invitations/{token}', TeamInvitationController::class)->name('teams.invitation');
        Blade::if('teamcan', function (string $permission, ?Model $team = null) {
            $team ??= Filament::getTenant();
            $user = Filament::auth()->user();

            return $team && $user && app(Teams::class)->hasTeamPermission($user, $team, $permission);
        });
        Event::listen(Login::class, [InvitationContext::class, 'login']);
        Event::listen(Registered::class, [PersonalTeams::class, 'handle']);
        Event::listen(Verified::class, function (Verified $event) {
            if (config('teams.enabled')) {
                app(Invitations::class)->acceptPending($event->user, app(Teams::class)->guard());
            }
        });
        if ($this->app->runningInConsole()) {
            $this->commands([PruneTeamInvitations::class, SeedShieldRoles::class]);
            $this->publishesMigrations([__DIR__.'/../../database/migrations/2026_01_05_000000_create_team_invitations.php' => database_path('migrations/2026_01_05_000000_create_team_invitations.php')], 'filament-tenancy-teams-migrations');
            $this->publishes([__DIR__.'/../../resources/views' => resource_path('views/vendor/filament-tenancy')], 'filament-tenancy-views');
            $this->publishes([__DIR__.'/../../config/teams.php' => config_path('teams.php')], 'filament-tenancy-teams-config');
            $this->publishesMigrations([__DIR__.'/../../database/optional-migrations' => database_path('migrations')], 'filament-tenancy-notifications-migration');
            $this->publishesMigrations([__DIR__.'/../../database/optional-migrations/2026_09_22_000000_fix_postgres_notification_data_type.php' => database_path('migrations/2026_09_22_000000_fix_postgres_notification_data_type.php')], 'filament-tenancy-notifications-upgrade');
            $this->publishes([__DIR__.'/../../resources/lang' => lang_path('vendor/filament-tenancy')], 'filament-tenancy-translations');
        }
    }
}
