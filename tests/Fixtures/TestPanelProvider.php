<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Filament\Auth\Pages\Register;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Liern\FilamentTenancy\TenancyPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->login()
            ->registration(config('teams.enabled') ? Register::class : null)
            ->pages([Dashboard::class])
            ->resources([Notes\NoteResource::class])
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, SubstituteBindings::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class])
            ->plugin(TenancyPlugin::make());
    }
}
