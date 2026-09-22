<?php

namespace Liern\FilamentTenancy\Tests;

use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Http\Middleware\SetTeamPermissions;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidRoleException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsConfigurationException;
use Liern\FilamentTenancy\Teams\Shield;
use Liern\FilamentTenancy\Teams\Teams;
use Liern\FilamentTenancy\Tests\Fixtures\CentralPermission;
use Liern\FilamentTenancy\Tests\Fixtures\CentralRole;
use Liern\FilamentTenancy\Tests\Fixtures\ShieldUser;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

class ShieldTeamsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PermissionServiceProvider::class,
            FilamentShieldServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set([
            'teams.enabled' => true, 'teams.shield.enabled' => true,
            'filament-tenancy.user_model' => ShieldUser::class,
            'auth.providers.users.model' => ShieldUser::class,
            'permission.teams' => true,
            'permission.models.role' => CentralRole::class,
            'permission.models.permission' => CentralPermission::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        (require __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);
    }

    protected function fixtures(): array
    {
        $owner = ShieldUser::create(['name' => 'Owner', 'email' => 'owner@example.test']);
        $user = ShieldUser::create(['name' => 'Member', 'email' => 'member@example.test']);
        $a = app(CreateWorkspace::class)->create($owner, ['name' => 'One', 'slug' => 'one']);
        $b = app(CreateWorkspace::class)->create($owner, ['name' => 'Two', 'slug' => 'two']);
        $member = CentralRole::create(['name' => 'member', 'guard_name' => 'web', 'team_id' => null]);
        $manager = CentralRole::create(['name' => 'manager', 'guard_name' => 'web', 'team_id' => null]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return [$owner, $user, $a, $b, $member, $manager, app(Teams::class)];
    }

    public function test_role_assignment_is_team_scoped_and_preserves_unrelated_roles(): void
    {
        [$owner, $user, $a, $b, $member, $manager, $teams] = $this->fixtures();
        $extra = CentralRole::create(['name' => 'auditor', 'guard_name' => 'web', 'team_id' => null]);
        $teams->addMember($owner, $a, $user, 'member');
        $teams->addMember($owner, $b, $user, 'member');
        app(Shield::class)->context($a, $user, fn () => $user->assignRole($extra));
        $teams->changeRole($owner, $a, $user, 'manager');
        app(Shield::class)->context($a, $user, function () use ($user) {
            $this->assertTrue($user->hasRole('manager'));
            $this->assertTrue($user->hasRole('auditor'));
            $this->assertFalse($user->hasRole('member'));
        });
        app(Shield::class)->context($b, $user, fn () => $this->assertTrue($user->hasRole('member')));
        $teams->removeMember($owner, $a, $user);
        app(Shield::class)->context($a, $user, function () use ($user) {
            $this->assertFalse($user->hasRole('manager'));
            $this->assertTrue($user->hasRole('auditor'));
        });
        app(Shield::class)->context($b, $user, fn () => $this->assertTrue($user->hasRole('member')));
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_role_choices_exclude_other_teams_guards_and_super_admin(): void
    {
        [$owner, $user, $a, $b, $member, $manager, $teams] = $this->fixtures();
        foreach ([['local', 'web', $a->id], ['foreign', 'web', $b->id], ['api', 'api', null], ['super_admin', 'web', null], ['owner', 'web', null]] as [$name, $guard, $team]) {
            CentralRole::create(['name' => $name, 'guard_name' => $guard, 'team_id' => $team]);
        }
        $roles = $teams->roles($a, $owner);
        $this->assertArrayHasKey('local', $roles);
        foreach (['foreign', 'api', 'super_admin', 'owner'] as $excluded) {
            $this->assertArrayNotHasKey($excluded, $roles);
        }
        $this->expectException(InvalidRoleException::class);
        $teams->addMember($owner, $a, $user, 'foreign');
    }

    public function test_user_can_follows_current_tenant_and_clears_context_on_exceptions(): void
    {
        [$owner, $user, $a, $b, $member, $manager, $teams] = $this->fixtures();
        $permission = CentralPermission::create(['name' => 'View:Report', 'guard_name' => 'web']);
        $manager->givePermissionTo($permission);
        $teams->addMember($owner, $a, $user, 'manager');
        $teams->addMember($owner, $b, $user, 'member');
        $this->actingAs($user);
        foreach ([$a, $b, $a] as $team) {
            Filament::setTenant($team);
            app(SetTeamPermissions::class)->handle(Request::create('/'), function () use ($user, $team, $a) {
                $this->assertSame($team->id === $a->id, $user->can('View:Report'));
            });
            $this->assertNull(getPermissionsTeamId());
        }
        Filament::setTenant($a);
        try {
            app(SetTeamPermissions::class)->handle(Request::create('/'), fn () => throw new \RuntimeException('test'));
        } catch (\RuntimeException) {
        }
        $this->assertNull(getPermissionsTeamId());
        $this->assertFalse($user->relationLoaded('roles'));
        $this->assertTrue($teams->hasTeamPermission($user, $a, 'View:Report'));
        $this->assertFalse($teams->hasTeamPermission($user, $b, 'View:Report'));
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_owner_does_not_gain_business_permissions_or_super_admin(): void
    {
        [$owner, $user, $a, $b, $member, $manager, $teams] = $this->fixtures();
        CentralPermission::create(['name' => 'Delete:Report', 'guard_name' => 'web']);
        $this->assertTrue($teams->hasTeamPermission($owner, $a, 'members.invite'));
        $this->assertFalse($teams->hasTeamPermission($owner, $a, 'Delete:Report'));
        app(Shield::class)->context($a, $owner, fn () => $this->assertFalse($owner->hasRole('super_admin')));
    }

    public function test_shield_configuration_fails_explicitly_when_teams_mode_is_off(): void
    {
        config(['permission.teams' => false]);
        $this->expectException(TeamsConfigurationException::class);
        app(Shield::class)->validate();
    }

    public function test_roles_remain_central_after_dedicated_database_switch(): void
    {
        [$owner, $user, $a, $b, $member, $manager, $teams] = $this->fixtures();
        tenancy()->initialize($a);
        $teams->addMember($owner, $a, $user, 'member');
        $this->assertSame('member', $teams->role($a, $user));
        $this->assertSame('central', (new CentralRole)->getConnectionName());
        app(Shield::class)->context($a, $user, fn () => $this->assertTrue($user->hasRole('member')));
    }
}
