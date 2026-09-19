<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Support\TenantModel;

class RetryProvisioning extends Command
{
    protected $signature = 'workspaces:retry {tenant : Workspace ID}';

    protected $description = 'Requeue provisioning for an unfinished workspace';

    public function handle(): int
    {
        $tenant = TenantModel::get()::find($this->argument('tenant'));
        if (! $tenant || $tenant->status === ProvisioningStatus::Ready) {
            $this->error('Workspace must exist and not already be ready.');

            return self::FAILURE;
        }

        ProvisionWorkspace::dispatch($tenant->getKey());
        $this->info('Provisioning queued.');

        return self::SUCCESS;
    }
}
