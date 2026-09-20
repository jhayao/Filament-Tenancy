<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Models\WorkspaceDomain;

class ListWorkspaceDomains extends Command
{
    protected $signature = 'workspaces:domains:list {tenant? : Optional workspace ID}';

    protected $description = 'List workspace custom domains';

    public function handle(): int
    {
        WorkspaceDomain::query()
            ->when($this->argument('tenant'), fn ($query, $tenant) => $query->where('workspace_id', $tenant))
            ->orderBy('workspace_id')
            ->orderBy('domain')
            ->each(fn (WorkspaceDomain $domain) => $this->line(sprintf(
                '%s\tworkspace=%s\t%s',
                $domain->domain,
                $domain->workspace_id,
                $domain->isVerified() ? 'verified' : 'pending',
            )));

        return self::SUCCESS;
    }
}
