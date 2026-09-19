<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Pages\Page;

class WorkspaceBilling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament-tenancy::pages.workspace-billing';

    public static function getNavigationLabel(): string
    {
        return 'Billing';
    }

    public function mount(): void
    {
        // Integration point for billing providers like Cashier or Lemon Squeezy
    }
}
