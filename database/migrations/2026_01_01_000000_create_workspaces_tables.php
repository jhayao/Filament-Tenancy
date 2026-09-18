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
        Schema::connection($this->getConnection())->create('workspaces', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('status')->default('pending')->index();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->getConnection())->create('workspace_user', function (Blueprint $table) {
            $table->string('workspace_id');
            // String keys support numeric, UUID and ULID user IDs without owning the users table.
            $table->string('user_id');
            $table->boolean('is_owner')->default(false);
            $table->timestamps();
            $table->primary(['workspace_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('workspace_user');
        Schema::connection($this->getConnection())->dropIfExists('workspaces');
    }
};
