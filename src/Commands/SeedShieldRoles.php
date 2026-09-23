<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\Teams\ShieldRoleSeeder;
use Throwable;

class SeedShieldRoles extends Command
{
    protected $signature = 'workspaces:seed-roles {workspace? : Workspace ID} {--all : Seed every workspace}';

    protected $description = 'Seed configured Shield roles for one or more workspaces';

    public function handle(ShieldRoleSeeder $seeder): int
    {
        if (! $seeder->enabled()) {
            $this->error('Shield role seeding requires teams.shield.enabled and teams.shield.seeding.enabled.');

            return self::FAILURE;
        }

        if ($this->option('all') && $this->argument('workspace')) {
            $this->error('Pass either a workspace ID or --all, not both.');

            return self::FAILURE;
        }

        if (! $this->option('all') && ! $this->argument('workspace')) {
            $this->error('Pass a workspace ID or --all.');

            return self::FAILURE;
        }

        $workspaces = $this->option('all')
            ? TenantModel::get()::query()->get()
            : TenantModel::get()::query()->whereKey($this->argument('workspace'))->get();

        if ($workspaces->isEmpty()) {
            $this->error('No matching workspace was found.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($workspaces as $workspace) {
            try {
                $result = $seeder->seed($workspace);
                $this->info("Workspace {$workspace->getKey()}: {$result['roles']} roles and {$result['permissions']} permissions created; {$result['roles_reused']} roles and {$result['permissions_reused']} permissions reused.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("Workspace {$workspace->getKey()}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
