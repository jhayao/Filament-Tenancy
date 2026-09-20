<?php

namespace Liern\FilamentTenancy\Billing;

use Filament\Billing\Providers\Contracts\BillingProvider;
use Liern\FilamentTenancy\Pages\WorkspaceBilling;

class NullBillingProvider implements BillingProvider
{
    public function getRouteAction(): string
    {
        return WorkspaceBilling::class;
    }

    public function getSubscribedMiddleware(): string
    {
        return '';
    }
}
