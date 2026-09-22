<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $pivot = config('teams.pivot', 'workspace_user');
        $schema->table($pivot, function (Blueprint $table) {
            $table->string('role')->default('member');
            $table->string('managed_role_id')->nullable();
        });
        if ($owner = config('teams.owner_column', 'is_owner')) {
            DB::connection($this->getConnection())->table($pivot)->where($owner, true)->update(['role' => 'owner']);
        }
        $schema->create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('team_id');
            $table->string('inviter_id');
            $table->string('email')->nullable();
            $table->string('role');
            $table->string('panel_id');
            $table->string('guard');
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('use_limit')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();
            $table->index(['team_id', 'email']);
        });
        $schema->create('team_personal_teams', function (Blueprint $table) {
            $table->string('user_id')->primary();
            $table->string('team_id')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        $schema->dropIfExists('team_personal_teams');
        $schema->dropIfExists('team_invitations');
        $schema->table(config('teams.pivot', 'workspace_user'), fn (Blueprint $table) => $table->dropColumn(['role', 'managed_role_id']));
    }
};
