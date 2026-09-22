<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Teams\Invitation;

class PruneTeamInvitations extends Command
{
    protected $signature = 'teams:prune-invitations';

    protected $description = 'Prune expired and stale team invitations after the retention period';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(0, (int) config('teams.retention_days', 30)));
        $count = Invitation::query()->where(function ($q) use ($cutoff) {
            $q->where('expires_at', '<=', $cutoff)->orWhere('revoked_at', '<=', $cutoff)->orWhere('accepted_at', '<=', $cutoff)
                ->orWhere(fn ($q) => $q->whereColumn('uses', '>=', 'use_limit')->where('updated_at', '<=', $cutoff));
        })->delete();
        $this->info(__('filament-tenancy::teams.pruned', ['count' => $count]));

        return self::SUCCESS;
    }
}
