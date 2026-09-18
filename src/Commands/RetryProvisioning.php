<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Models\Tenant;

class RetryProvisioning extends Command
{
    protected $signature = 'workspaces:retry {tenant : Workspace UUID}';
    protected $description = 'Requeue provisioning for a pending or failed workspace';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant || ! in_array($tenant->status, [ProvisioningStatus::Pending, ProvisioningStatus::Failed], true)) {
            $this->error('Workspace must exist and be pending or failed.');

            return self::FAILURE;
        }

        ProvisionWorkspace::dispatch($tenant->getKey());
        $this->info('Provisioning queued.');

        return self::SUCCESS;
    }
}
