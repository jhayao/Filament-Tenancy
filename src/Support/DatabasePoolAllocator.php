<?php

namespace Liern\FilamentTenancy\Support;

use LogicException;

class DatabasePoolAllocator
{
    public function validate(): void
    {
        [$connections, $strategy, $weights] = $this->configuration();

        if ($connections === []) {
            return;
        }

        $central = (string) config('filament-tenancy.central_connection');
        foreach ($connections as $connection) {
            if ($connection === 'tenant' || $connection === $central) {
                throw new LogicException("Database pool connection [{$connection}] is reserved.");
            }

            if (! array_key_exists($connection, config('database.connections', []))) {
                throw new LogicException("Database pool connection [{$connection}] is not configured.");
            }
        }

        if ($strategy === 'weighted') {
            foreach ($connections as $connection) {
                if (isset($weights[$connection]) && (float) $weights[$connection] <= 0) {
                    throw new LogicException("Database pool weight for [{$connection}] must be greater than zero.");
                }
            }
        }
    }

    public function allocate(): ?string
    {
        [$connections, $strategy, $weights] = $this->configuration();

        if ($connections === []) {
            return null;
        }

        $counts = [];
        foreach ($connections as $connection) {
            $counts[$connection] = $this->tenantCount($connection);
        }

        return match ($strategy) {
            'round-robin' => $connections[array_sum($counts) % count($connections)],
            'least-tenants' => $this->leastLoaded($connections, $counts),
            'weighted' => $this->weighted($connections, $counts, $weights),
            default => throw new LogicException("Unknown database pool strategy [{$strategy}]."),
        };
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        [$connections] = $this->configuration();

        return collect($connections)->mapWithKeys(fn (string $connection): array => [$connection => $this->tenantCount($connection)])->all();
    }

    /**
     * @return array{0: list<string>, 1: string, 2: array<string, int|float>}
     */
    protected function configuration(): array
    {
        $configured = config('filament-tenancy.database_pool', []);

        // Preserve the original public shape: ['connection_a', 'connection_b'].
        if (array_is_list($configured)) {
            return [array_values(array_filter($configured, 'is_string')), 'round-robin', []];
        }

        if (! is_array($configured) || ! ($configured['enabled'] ?? false)) {
            return [[], 'round-robin', []];
        }

        $connections = array_values(array_filter($configured['connections'] ?? [], 'is_string'));
        $strategy = (string) ($configured['strategy'] ?? 'least-tenants');
        $weights = $configured['weights'] ?? [];

        if (! in_array($strategy, ['least-tenants', 'round-robin', 'weighted'], true)) {
            throw new LogicException("Unknown database pool strategy [{$strategy}].");
        }

        if (! is_array($weights)) {
            throw new LogicException('Database pool weights must be an array.');
        }

        foreach ($weights as $connection => $weight) {
            if (! is_numeric($weight) || (float) $weight <= 0) {
                throw new LogicException("Database pool weight for [{$connection}] must be greater than zero.");
            }
        }

        return [$connections, $strategy, $weights];
    }

    protected function tenantCount(string $connection): int
    {
        return TenantModel::get()::query()
            ->get()
            ->filter(fn ($tenant): bool => $tenant->getAttribute('tenancy_db_connection') === $connection)
            ->count();
    }

    /**
     * @param  list<string>  $connections
     * @param  array<string, int>  $counts
     */
    protected function leastLoaded(array $connections, array $counts): string
    {
        return collect($connections)->sortBy(fn (string $connection): int => $counts[$connection])->first();
    }

    /**
     * @param  list<string>  $connections
     * @param  array<string, int>  $counts
     * @param  array<string, int|float>  $weights
     */
    protected function weighted(array $connections, array $counts, array $weights): string
    {
        return collect($connections)->sortBy(function (string $connection) use ($counts, $weights): float {
            return $counts[$connection] / (float) ($weights[$connection] ?? 1);
        })->first();
    }
}
