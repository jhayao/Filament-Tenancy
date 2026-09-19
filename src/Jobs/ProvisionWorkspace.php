<?php

namespace Liern\FilamentTenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Support\TenantModel;
use RuntimeException;
use Throwable;

class ProvisionWorkspace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [30, 120];

    public function __construct(public string $tenantId)
    {
        $this->onConnection(config('filament-tenancy.queue_connection'));
        $this->onQueue(config('filament-tenancy.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('workspace:'.$this->tenantId))->releaseAfter(30)->expireAfter(360)];
    }

    public function handle(): void
    {
        $tenant = TenantModel::get()::findOrFail($this->tenantId);
        if ($tenant->status === ProvisioningStatus::Ready) {
            return;
        }

        $tenant->update(['status' => ProvisioningStatus::Provisioning]);

        try {
            $database = $tenant->database();
            $database->makeCredentials();
            $manager = $database->manager();

            if (! $manager->databaseExists($database->getName())) {
                $manager->createDatabase($tenant);
            }

            $previousTenant = tenant();
            try {
                tenancy()->initialize($tenant);
                $path = config('filament-tenancy.migration_path');
                if (! is_dir($path)) {
                    throw new RuntimeException('The tenant migration directory does not exist: '.$path);
                }

                if (Artisan::call('migrate', ['--database' => 'tenant', '--path' => [$path], '--realpath' => true, '--force' => true]) !== 0) {
                    throw new RuntimeException('Tenant migrations failed.');
                }

                if ($seeder = config('filament-tenancy.seeder')) {
                    if (Artisan::call('db:seed', ['--database' => 'tenant', '--class' => $seeder, '--force' => true]) !== 0) {
                        throw new RuntimeException('Tenant seeding failed.');
                    }
                }
            } finally {
                tenancy()->end();
                if ($previousTenant) {
                    tenancy()->initialize($previousTenant);
                }
            }

            $tenant->update(['status' => ProvisioningStatus::Ready]);
        } catch (Throwable $exception) {
            $tenant->update(['status' => ProvisioningStatus::Failed]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        TenantModel::get()::whereKey($this->tenantId)
            ->where('status', '!=', ProvisioningStatus::Ready->value)
            ->update(['status' => ProvisioningStatus::Failed->value]);
    }
}
