<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('filament-tenancy.central_connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('workspace_handoffs', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('user_id', 255);
            $table->string('guard', 100);
            $table->string('panel_id', 100);
            $table->string('target_host', 253);
            $table->string('browser_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id']);
            $table->index(['target_host', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('workspace_handoffs');
    }
};
