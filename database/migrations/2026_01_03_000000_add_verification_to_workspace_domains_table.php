<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('filament-tenancy.central_connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->table('workspace_domains', function (Blueprint $table) {
            $table->string('verification_token', 128)->nullable()->unique()->after('workspace_id');
            $table->timestamp('verified_at')->nullable()->after('verification_token');
        });

        DB::connection($this->getConnection())
            ->table('workspace_domains')
            ->whereNull('verification_token')
            ->orderBy('id')
            ->eachById(function (object $domain): void {
                DB::connection($this->getConnection())
                    ->table('workspace_domains')
                    ->where('id', $domain->id)
                    ->update(['verification_token' => Str::random(64)]);
            });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('workspace_domains', function (Blueprint $table) {
            $table->dropUnique(['verification_token']);
            $table->dropColumn(['verification_token', 'verified_at']);
        });
    }
};
