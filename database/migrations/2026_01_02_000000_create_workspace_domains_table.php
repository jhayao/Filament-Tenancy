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
        Schema::connection($this->getConnection())->create('workspace_domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->string('workspace_id');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('workspace_domains');
    }
};
