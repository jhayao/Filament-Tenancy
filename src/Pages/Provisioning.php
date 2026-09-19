<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Support\TenantModel;

class Provisioning extends Page
{
    protected static ?string $slug = 'workspace-setup';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-tenancy::provisioning';

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected array $extraBodyAttributes = ['class' => 'lw-standalone-page'];

    public function getTitle(): string
    {
        return __('filament-tenancy::tenancy.setup');
    }

    protected function getWorkspace(): Tenant
    {
        $tenant = Filament::getTenant();
        abort_unless(is_a($tenant, TenantModel::get()) && Filament::auth()->user()?->canAccessTenant($tenant), 404);

        return $tenant->refresh();
    }

    protected function getViewData(): array
    {
        $tenant = $this->getWorkspace();

        return [
            'workspaceName' => $tenant->name,
            'status' => $tenant->status,
            'dashboardUrl' => Filament::getUrl($tenant),
        ];
    }

    protected function getLayoutData(): array
    {
        return ['hasTopbar' => false];
    }

    public function checkStatus(): void
    {
        $this->getWorkspace();
    }

    public function retryProvisioning(): void
    {
        $tenant = $this->getWorkspace();
        abort_unless($tenant->status === ProvisioningStatus::Failed, 404);

        $tenant->update(['status' => ProvisioningStatus::Pending]);
        ProvisionWorkspace::dispatch($tenant->getKey());
    }
}
