<?php

namespace Liern\FilamentTenancy\Commands;

use Illuminate\Console\Command;
use Liern\FilamentTenancy\Support\DatabasePoolAllocator;
use Throwable;

class DatabasePoolStatus extends Command
{
    protected $signature = 'tenants:pool {--check : Return a failure status when the pool configuration is invalid}';

    protected $description = 'Inspect workspace database pool placement';

    public function handle(DatabasePoolAllocator $allocator): int
    {
        try {
            $allocator->validate();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $allocator->counts();
        if ($counts === []) {
            $this->info('Workspace database pooling is disabled.');

            return self::SUCCESS;
        }

        foreach ($counts as $connection => $count) {
            $this->line($connection.'\ttenants='.$count);
        }

        return self::SUCCESS;
    }
}
