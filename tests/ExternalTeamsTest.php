<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Teams\Teams;
use Liern\FilamentTenancy\TenancyPlugin;
use Liern\FilamentTenancy\Tests\Fixtures\ExternalTeam;

class ExternalTeamsTest extends TestCase
{
    public function test_external_preset_uses_string_keys_and_trait_free_models_without_provisioning(): void
    {
        $panel = Panel::make()->id('external')->path('external');
        TenancyPlugin::make()->useFilamentTenancy(ExternalTeam::class)->register($panel);
        $this->assertSame('tenant_user', config('teams.pivot'));
        $this->assertNull($panel->getTenantRegistrationPage());
        $this->assertNotContains(Provisioning::class, $panel->getPages());
        Schema::create('external_teams', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('role')->default('member');
            $table->string('managed_role_id')->nullable();
            $table->timestamps();
            $table->primary(['tenant_id', 'user_id']);
        });
        $team = ExternalTeam::create(['id' => 'tenant-string-id', 'name' => 'External']);
        $owner = $this->user();
        $member = $this->user('member@example.test');
        // Use ordinary Eloquent models without any package traits for all actor lookups.
        config(['teams.user_model' => PlainUser::class]);
        $teams = app(Teams::class);
        $owner = $teams->user($owner->id);
        $member = $teams->user($member->id);
        $teams->initializeOwner($team, $owner);
        $teams->addMember($owner, $team, $member, 'member');
        $this->assertSame(2, $teams->members($team)->count());
        $this->assertSame('tenant-string-id', $teams->forUser($member)->first()->getKey());
        $this->assertTrue($teams->hasTeamPermission($owner, $team, 'members.invite'));
        $this->assertSame('central', $teams->members($team)->getRelated()->getConnectionName());
    }
}

class PlainUser extends Model
{
    protected $table = 'users';

    protected $guarded = [];
}
