<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('teams.connection') ?? config('filament-tenancy.central_connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $isPostgres = $schema->getConnection()->getDriverName() === 'pgsql';
        if (! $schema->hasTable('notifications')) {
            $schema->create('notifications', function (Blueprint $table) use ($isPostgres) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('notifiable_type');
                $table->string('notifiable_id');
                $table->index(['notifiable_type', 'notifiable_id']);
                if ($isPostgres) {
                    $table->json('data');
                } else {
                    $table->text('data');
                }
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // The host may already own this table: never destroy its notifications.
    }
};
