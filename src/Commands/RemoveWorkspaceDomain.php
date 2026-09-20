<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Support\WorkspaceDomainManager;

class RemoveWorkspaceDomain extends Command
{
    protected $signature = 'workspaces:domains:remove {domain : Domain ID} {--force : Skip confirmation}';

    protected $description = 'Remove a workspace custom domain';

    public function handle(WorkspaceDomainManager $manager): int
    {
        $domain = WorkspaceDomain::query()->find($this->argument('domain'));

        if (! $domain) {
            $this->error('Custom domain not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Remove {$domain->domain} from workspace {$domain->workspace_id}?")) {
            return self::SUCCESS;
        }

        $manager->remove($domain);
        $this->info('Custom domain removed.');

        return self::SUCCESS;
    }
}
