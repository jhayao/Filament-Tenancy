<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Liern\FilamentTenancy\Pages\Members;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Teams\Auth\Login;
use Liern\FilamentTenancy\Teams\Events\InvitationAccepted;
use Liern\FilamentTenancy\Teams\Events\MemberAdded;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidInvitationException;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidRoleException;
use Liern\FilamentTenancy\Teams\Exceptions\OwnershipException;
use Liern\FilamentTenancy\Teams\Exceptions\ResendCooldownException;
use Liern\FilamentTenancy\Teams\Exceptions\SeatLimitException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamAuthorizationException;
use Liern\FilamentTenancy\Teams\Invitation;
use Liern\FilamentTenancy\Teams\InvitationMail;
use Liern\FilamentTenancy\Teams\Invitations;
use Liern\FilamentTenancy\Teams\Teams;
use Liern\FilamentTenancy\Tests\Fixtures\RestrictedImpersonationUser;
use Livewire\Livewire;
use STS\FilamentImpersonate\Facades\Impersonation;

class TeamsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        (require __DIR__.'/../database/optional-migrations/2026_01_05_000001_create_team_notifications.php')->up();
    }

    protected function workspace(): array
    {
        $owner = $this->user();
        $team = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($owner);
        Filament::setTenant($team);

        return [$owner, $team, app(Teams::class), app(Invitations::class)];
    }

    public function test_email_invitation_reserves_a_seat_and_acceptance_converts_it_once(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        config(['teams.seat_limit' => 2]);
        $user = $this->user('guest@example.test');
        Event::fake([InvitationAccepted::class, MemberAdded::class]);
        $invite = $invitations->create($owner, $team, 'member', $user->email);
        $this->assertTrue($teams->full($team));
        $this->assertSame(1, $teams->connection()->table('notifications')->count());
        Mail::assertSent(InvitationMail::class, 1);
        $invitations->accept($user, $invite->token);
        $invitations->accept($user, $invite->token);
        $this->assertSame(2, $teams->seats($team));
        $this->assertSame('member', $teams->role($team, $user));
        Event::assertDispatchedTimes(InvitationAccepted::class, 1);
        Event::assertDispatchedTimes(MemberAdded::class, 1);
    }

    public function test_bulk_invites_deduplicate_and_return_per_address_errors(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        config(['teams.seat_limit' => 2]);
        $results = $invitations->inviteMany($owner, $team, [' One@example.test ', 'one@example.test', 'bad', 'two@example.test'], 'member');
        $this->assertCount(3, $results);
        $this->assertInstanceOf(Invitation::class, $results['one@example.test']);
        $this->assertIsString($results['bad']);
        $this->assertIsString($results['two@example.test']);
        $again = $invitations->create($owner, $team, 'member', 'one@example.test');
        $this->assertSame($results['one@example.test']->id, $again->id);
        Mail::assertSent(InvitationMail::class, 1);
    }

    public function test_shareable_links_reserve_no_seats_and_have_an_atomic_use_limit(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $invite = $invitations->create($owner, $team, 'member');
        $this->assertSame(1, $teams->seats($team));
        $first = $this->user('first@example.test');
        $invitations->accept($first, $invite->token);
        $invitations->accept($first, $invite->token);
        $this->assertSame(1, $invite->fresh()->uses);
        $this->expectException(InvalidInvitationException::class);
        $invitations->accept($this->user('second@example.test'), $invite->token);
    }

    public function test_shareable_link_cannot_take_a_reserved_email_seat(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        config(['teams.seat_limit' => 2]);
        $link = $invitations->create($owner, $team, 'member');
        $invitations->create($owner, $team, 'member', 'reserved@example.test');
        $this->expectException(SeatLimitException::class);
        $invitations->accept($this->user('other@example.test'), $link->token);
    }

    public function test_resend_cooldown_rotation_and_revocation(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $invite = $invitations->create($owner, $team, 'member', 'guest@example.test');
        $old = $invite->token;
        try {
            $invitations->resend($owner, $invite);
            $this->fail('Resend should be throttled.');
        } catch (ResendCooldownException) {
        }
        $this->travel(61)->seconds();
        $invitations->resend($owner, $invite);
        $this->assertNotSame($old, $invite->token);
        $this->assertFalse(Invitation::where('token_hash', hash('sha256', $old))->exists());
        $invitations->revoke($owner, $invite);
        $this->assertSame(1, $teams->seats($team));
        $this->expectException(InvalidInvitationException::class);
        $invitations->accept($this->user('guest@example.test'), $invite->token);
    }

    public function test_manager_cannot_assign_manager_or_modify_owner(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $manager = $this->user('manager@example.test');
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $manager, 'manager');
        $teams->addMember($manager, $team, $member, 'member');
        try {
            $teams->changeRole($manager, $team, $member, 'manager');
            $this->fail('Manager escalated a member.');
        } catch (InvalidRoleException) {
        }
        $this->expectException(TeamAuthorizationException::class);
        $teams->removeMember($manager, $team, $owner);
    }

    public function test_owner_role_is_reserved_and_transfer_preserves_legacy_flags(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $member, 'member');
        try {
            $teams->changeRole($owner, $team, $member, 'owner');
            $this->fail('Owner role was assigned by hand.');
        } catch (InvalidRoleException) {
        }
        $teams->transferOwnership($owner, $team, $member);
        $this->assertSame('owner', $teams->role($team, $member));
        $this->assertSame('manager', $teams->role($team, $owner));
        $this->assertTrue((bool) $teams->membership($team, $member)->is_owner);
        $this->assertFalse((bool) $teams->membership($team, $owner)->is_owner);
        $teams->removeMember($owner, $team, $owner);
        $this->assertNull($teams->membership($team, $owner));
        $this->expectException(OwnershipException::class);
        $teams->removeMember($member, $team, $member);
    }

    public function test_automatic_acceptance_requires_verified_email_and_matches_guard(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $user = $this->user('guest@example.test');
        $invitations->create($owner, $team, 'member', $user->email);
        $invitations->acceptPending($user, 'web');
        $this->assertNull($teams->membership($team, $user));
        $user->setAttribute('email_verified_at', now());
        $invitations->acceptPending($user, 'other');
        $this->assertNull($teams->membership($team, $user));
        $invitations->acceptPending($user, 'web');
        $this->assertSame('member', $teams->role($team, $user));
    }

    public function test_expiry_and_email_mismatch_do_not_add_members(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $invite = $invitations->create($owner, $team, 'member', 'guest@example.test');
        try {
            $invitations->accept($this->user('wrong@example.test'), $invite->token);
            $this->fail('Wrong email accepted.');
        } catch (InvalidInvitationException $e) {
            $this->assertSame('email_mismatch', $e->reason);
        }
        $this->travel(8)->days();
        $this->assertSame(1, $teams->seats($team));
        $this->expectException(InvalidInvitationException::class);
        $invitations->accept($this->user('guest@example.test'), $invite->token);
    }

    public function test_members_page_and_forged_action_are_authorized_on_server(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $member, 'member');
        $url = Members::getUrl(tenant: $team);
        $this->get($url)->assertOk()->assertSee('Members');
        Filament::setTenant($team);
        Livewire::test(Members::class)
            ->assertSee('People with access to this workspace.')
            ->assertSee('Pending invitations')
            ->assertSeeHtml('>2</p>')
            ->assertSee('/ ∞')
            ->assertSee($member->email)
            ->assertSee('No invitations yet.')
            ->assertSee('Impersonate');
        Filament::setTenant($team);
        $this->actingAs($member);
        Livewire::test(Members::class)->assertSee($owner->email)
            ->callAction('role', ['role' => 'manager'], arguments: ['user' => $member->id]);
        $this->assertSame('member', $teams->role($team, $member));
        $this->actingAs($this->user('stranger@example.test'))->get($url)->assertNotFound();
    }

    public function test_workspace_owner_can_impersonate_another_current_workspace_member(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $member, 'member');

        $outside = $this->user('outside@example.test');
        $component = Livewire::test(Members::class);
        $this->assertFalse($component->instance()->mayImpersonate($outside));
        $component->mountAction('impersonate', ['user' => (string) $outside->getKey()])
            ->callMountedAction();
        $this->assertAuthenticatedAs($owner);
        $this->assertFalse(Impersonation::isImpersonating());

        Livewire::test(Members::class)
            ->callAction('impersonate', arguments: ['user' => (string) $member->getKey()])
            ->assertRedirect(Filament::getUrl($team));

        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($member);
    }

    public function test_only_workspace_owners_can_impersonate_current_workspace_members(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $manager = $this->user('manager@example.test');
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $manager, 'manager');
        $teams->addMember($owner, $team, $member, 'member');

        Filament::setTenant($team);
        $this->actingAs($manager);
        $component = Livewire::test(Members::class)->assertDontSee('Impersonate');
        $this->assertFalse($component->instance()->mayImpersonate($member));
        $this->assertFalse($component->instance()->mayImpersonate($owner));
        $component->mountAction('impersonate', ['user' => (string) $member->getKey()])
            ->callMountedAction();
        $this->assertAuthenticatedAs($manager);
        $this->assertFalse(Impersonation::isImpersonating());
    }

    public function test_impersonation_respects_the_host_users_can_be_impersonated_hook(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        config([
            'filament-tenancy.user_model' => RestrictedImpersonationUser::class,
            'auth.providers.users.model' => RestrictedImpersonationUser::class,
        ]);
        $member = RestrictedImpersonationUser::create(['name' => 'Restricted', 'email' => 'restricted@example.test']);
        $teams->addMember($owner, $team, $member, 'member');

        Filament::setTenant($team);
        $component = Livewire::test(Members::class)->assertDontSee('Impersonate');
        $this->assertFalse($component->instance()->mayImpersonate($member));
        $component->mountAction('impersonate', ['user' => (string) $member->getKey()])
            ->callMountedAction();

        $this->assertAuthenticatedAs($owner);
        $this->assertFalse(Impersonation::isImpersonating());
    }

    public function test_owner_can_disable_impersonation_using_the_host_users_hook(): void
    {
        [$owner, $team, $teams] = $this->workspace();
        $member = $this->user('member@example.test');
        $teams->addMember($owner, $team, $member, 'member');
        $restrictedOwner = RestrictedImpersonationUser::findOrFail($owner->getKey());

        Filament::setTenant($team);
        $this->actingAs($restrictedOwner);
        $component = Livewire::test(Members::class)->assertDontSee('Impersonate');
        $this->assertFalse($component->instance()->mayImpersonate($member));
        $component->mountAction('impersonate', ['user' => (string) $member->getKey()])
            ->callMountedAction();

        $this->assertAuthenticatedAs($restrictedOwner);
        $this->assertFalse(Impersonation::isImpersonating());
    }

    public function test_guest_invitation_stages_login_and_signed_in_acceptance_is_post_only(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $invite = $invitations->create($owner, $team, 'member', 'guest@example.test');
        auth()->logout();
        $this->get($invite->url())->assertRedirect(Filament::getLoginUrl())->assertSessionHas('teams.invitation', $invite->token);
        $guest = $this->user('guest@example.test');
        $this->actingAs($guest)->get($invite->url())->assertOk()->assertSee('Join Acme');
        $this->assertNull($teams->membership($team, $guest));
        $this->post($invite->url())->assertRedirect();
        $this->assertSame('member', $teams->role($team, $guest));
    }

    public function test_invitation_login_locks_email_and_accepts_after_authentication(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $user = $this->user('guest@example.test');
        $user->update(['password' => bcrypt('password')]);
        $invite = $invitations->create($owner, $team, 'member', $user->email);
        auth()->logout();
        session()->put('teams.invitation', $invite->token);
        Livewire::test(Login::class)
            ->assertSet('data.email', $user->email)
            ->fillForm(['email' => 'wrong@example.test', 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('teams.error'));
        $this->assertNull(session('teams.invitation'));
        $this->assertSame('member', $teams->role($team, $user));
    }

    public function test_pruning_keeps_recent_and_active_invitations(): void
    {
        [$owner, $team, $teams, $invitations] = $this->workspace();
        $old = $invitations->create($owner, $team, 'member');
        $old->update(['expires_at' => now()->subDays(31)]);
        $recent = $invitations->create($owner, $team, 'member');
        $recent->update(['expires_at' => now()->subDay()]);
        $active = $invitations->create($owner, $team, 'member');
        $this->artisan('teams:prune-invitations')->assertSuccessful();
        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($active->fresh());
    }

    public function test_personal_team_creation_is_optional_and_idempotent(): void
    {
        $user = $this->user();
        event(new Registered($user));
        $this->assertSame(0, $user->workspaces()->count());
        config(['teams.personal_teams' => true]);
        event(new Registered($user));
        event(new Registered($user));
        $this->assertSame(1, $user->workspaces()->count());
        $this->assertSame('owner', app(Teams::class)->role($user->workspaces()->first(), $user));
    }
}
