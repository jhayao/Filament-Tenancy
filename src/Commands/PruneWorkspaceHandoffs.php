<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Models\WorkspaceHandoff;

class PruneWorkspaceHandoffs extends Command
{
    protected $signature = 'workspaces:handoffs:prune';

    protected $description = 'Remove expired workspace login handoffs';

    public function handle(): int
    {
        WorkspaceHandoff::query()
            ->where(function ($query) {
                $query
                    ->where('expires_at', '<', now()->subHour())
                    ->orWhere('used_at', '<', now()->subHour());
            })
            ->delete();

        return self::SUCCESS;
    }
}
