<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Models\Tenant;

class Provisioning extends Page
{
    protected static ?string $slug = 'workspace-setup';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-tenancy::provisioning';

    protected static string $layout = 'filament-panels::components.layout.simple';

    public function getTitle(): string
    {
        return __('filament-tenancy::tenancy.setup');
    }

    protected function getWorkspace(): Tenant
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Tenant && Filament::auth()->user()?->canAccessTenant($tenant), 404);

        return $tenant->refresh();
    }

    protected function getViewData(): array
    {
        $tenant = $this->getWorkspace();

        return ['workspaceName' => $tenant->name, 'status' => $tenant->status];
    }

    public function checkStatus(): void
    {
        $tenant = $this->getWorkspace();
        if ($tenant->status === ProvisioningStatus::Ready) {
            $this->redirect(Filament::getUrl($tenant));
        }
    }
}
