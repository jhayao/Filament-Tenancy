<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Support\WorkspaceDomainManager;

class VerifyWorkspaceDomain extends Command
{
    protected $signature = 'workspaces:domains:verify {domain : Domain ID}';

    protected $description = 'Verify a workspace custom domain DNS record';

    public function handle(WorkspaceDomainManager $manager): int
    {
        $domain = WorkspaceDomain::query()->find($this->argument('domain'));

        if (! $domain) {
            $this->error('Custom domain not found.');

            return self::FAILURE;
        }

        if ($manager->verify($domain, 'lona-domain-verify:console:'.$domain->getKey())) {
            $this->info('Custom domain verified.');

            return self::SUCCESS;
        }

        $this->warn('The verification TXT record was not found.');

        return self::FAILURE;
    }
}
