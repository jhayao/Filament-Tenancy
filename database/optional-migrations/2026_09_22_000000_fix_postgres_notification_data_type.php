<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('teams.connection') ?? config('filament-tenancy.central_connection');
    }

    public function up(): void
    {
        $connection = DB::connection($this->getConnection());
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = $connection->getSchemaBuilder();
        if (! $schema->hasTable('notifications') || ! $schema->hasColumn('notifications', 'data')) {
            return;
        }

        if (in_array($schema->getColumnType('notifications', 'data'), ['json', 'jsonb'], true)) {
            return;
        }

        $table = $connection->getQueryGrammar()->wrapTable('notifications');

        // PostgreSQL validates every payload atomically. Invalid JSON aborts the
        // conversion without changing existing rows or dropping their metadata.
        $connection->statement('ALTER TABLE '.$table.' ALTER COLUMN "data" TYPE json USING "data"::json');
    }

    public function down(): void
    {
        // JSON remains compatible with older package versions. Preserve the
        // working type, including host-owned columns that were already JSON.
    }
};
