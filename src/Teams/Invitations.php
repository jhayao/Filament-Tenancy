<?php

namespace Liern\FilamentTenancy\Teams;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Liern\FilamentTenancy\Teams\Events\InvitationAccepted;
use Liern\FilamentTenancy\Teams\Events\MemberInvited;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidInvitationException;
use Liern\FilamentTenancy\Teams\Exceptions\ResendCooldownException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamAuthorizationException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsException;

class Invitations
{
    public function __construct(protected Teams $teams) {}

    public function inviteMany(Model $actor, Model $team, array $emails, string $role): array
    {
        $results = [];
        foreach (array_unique(array_map(fn ($email) => strtolower(trim((string) $email)), $emails)) as $email) {
            try {
                Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->validate();
                $results[$email] = $this->create($actor, $team, $role, $email);
            } catch (TeamsException|ValidationException $exception) {
                $results[$email] = $exception->getMessage();
            }
        }

        return $results;
    }

    public function create(Model $actor, Model $team, string $role, ?string $email = null, ?CarbonInterface $expiresAt = null, int $useLimit = 1): Invitation
    {
        $email = $email === null ? null : strtolower(trim($email));
        Validator::make(['email' => $email, 'expires_at' => $expiresAt, 'use_limit' => $useLimit], [
            'email' => ['nullable', 'email:rfc', 'max:255'], 'expires_at' => ['nullable', 'date', 'after:now'], 'use_limit' => ['integer', 'min:1', 'max:1000000'],
        ])->validate();

        return $this->teams->locked($team, function () use ($actor, $team, $role, $email, $expiresAt, $useLimit) {
            $this->teams->authorizeManager($team, $actor);
            $this->teams->assertRole($team, $role, $actor);
            if ($email !== null) {
                if ($this->teams->members($team)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw new InvalidInvitationException('already_member');
                }
                $existing = $this->active($team)->where('email', $email)->first();
                if ($existing) {
                    return $existing;
                }
            }
            $this->teams->assertCapacity($team);
            $token = Str::random(64);
            $invitation = Invitation::create([
                'team_id' => (string) $team->getKey(), 'inviter_id' => (string) $actor->getKey(),
                'email' => $email, 'role' => $role, 'panel_id' => Filament::getCurrentPanel()?->getId() ?? Filament::getDefaultPanel()->getId(),
                'guard' => $this->teams->guard(), 'token' => $token, 'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt ?? now()->addDays(config('teams.expiry_days')),
                'use_limit' => $email ? 1 : $useLimit, 'last_sent_at' => $email ? now() : null,
            ]);
            $this->teams->emit(new MemberInvited($team, $invitation));
            if ($email) {
                $this->teams->connection()->afterCommit(fn () => $this->deliver($invitation));
            }

            return $invitation;
        });
    }

    public function active(Model $team): Builder
    {
        return Invitation::query()->where('team_id', (string) $team->getKey())->whereNull('revoked_at')
            ->whereNull('accepted_at')->where('expires_at', '>', now())->whereColumn('uses', '<', 'use_limit');
    }

    public function find(string $token): Invitation
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->first();
        if (! $invitation) {
            throw new InvalidInvitationException;
        }

        return $invitation;
    }

    public function resend(Model $actor, Invitation $invitation): Invitation
    {
        $team = $this->teams->team($invitation->team_id);

        return $this->teams->locked($team, function () use ($actor, $invitation, $team) {
            $invitation->refresh();
            $this->teams->authorizeManager($team, $actor);
            $this->teams->assertRole($team, $invitation->role, $actor, $invitation->guard);
            if (! $invitation->email || $invitation->revoked_at || $invitation->accepted_at) {
                throw new InvalidInvitationException;
            }
            if ($invitation->last_sent_at?->addSeconds(config('teams.resend_cooldown'))->isFuture()) {
                throw new ResendCooldownException;
            }
            if ($this->active($team)->where('email', $invitation->email)->whereKeyNot($invitation->id)->exists()) {
                throw new InvalidInvitationException;
            }
            $this->teams->assertCapacity($team, $invitation->id);
            $token = Str::random(64);
            $invitation->update(['token' => $token, 'token_hash' => hash('sha256', $token), 'last_sent_at' => now(), 'expires_at' => now()->addDays(config('teams.expiry_days'))]);
            $this->teams->connection()->afterCommit(fn () => $this->deliver($invitation));

            return $invitation;
        });
    }

    public function revoke(Model $actor, Invitation $invitation): void
    {
        $team = $this->teams->team($invitation->team_id);
        $this->teams->locked($team, function () use ($actor, $team, $invitation) {
            $this->teams->authorizeManager($team, $actor);
            $this->teams->assertRole($team, $invitation->role, $actor, $invitation->guard);
            $invitation->update(['revoked_at' => now()]);
        });
    }

    public function accept(Model $user, string $token): Model
    {
        $invitation = $this->find($token);
        $team = $this->teams->team($invitation->team_id);

        return $this->teams->locked($team, function () use ($user, $invitation, $team, $token) {
            $invitation->refresh();
            if (! hash_equals($invitation->token_hash, hash('sha256', $token))) {
                throw new InvalidInvitationException;
            }
            $panel = Filament::getPanel($invitation->panel_id);
            if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
                throw new TeamAuthorizationException;
            }
            if ($invitation->email !== null && strtolower((string) $user->getAttribute('email')) !== $invitation->email) {
                throw new InvalidInvitationException('email_mismatch');
            }
            if ($invitation->revoked_at || $invitation->expires_at->isPast()) {
                throw new InvalidInvitationException;
            }
            if ($this->teams->membership($team, $user)) {
                // An email reservation is no longer needed if another path added this member.
                if ($invitation->email && ! $invitation->accepted_at) {
                    $invitation->update(['accepted_at' => now(), 'uses' => 1]);
                }

                return $team;
            }
            if (! $invitation->isActive()) {
                throw new InvalidInvitationException;
            }
            $issuer = $this->teams->user($invitation->inviter_id);
            $this->teams->authorizeManager($team, $issuer);
            $this->teams->assertRole($team, $invitation->role, $issuer, $invitation->guard);
            $this->teams->assertCapacity($team, $invitation->id);
            $this->teams->addMember($issuer, $team, $user, $invitation->role, $invitation->guard);
            $invitation->update(['uses' => $invitation->uses + 1, 'accepted_at' => $invitation->email ? now() : null]);
            $this->teams->emit(new InvitationAccepted($team, $user, $invitation));

            return $team;
        });
    }

    public function acceptPending(Model $user, string $guard): void
    {
        if (! config('teams.auto_accept') || ! method_exists($user, 'hasVerifiedEmail') || ! $user->hasVerifiedEmail()) {
            return;
        }
        $invitations = Invitation::query()->where('email', strtolower((string) $user->getAttribute('email')))
            ->where('guard', $guard)->whereNull('revoked_at')->whereNull('accepted_at')->where('expires_at', '>', now())->get();
        foreach ($invitations as $invitation) {
            try {
                $this->accept($user, $invitation->token);
            } catch (TeamsException|ModelNotFoundException $exception) {
                // A stale invitation must not prevent login or acceptance of another invitation.
                report($exception);
            }
        }
    }

    protected function deliver(Invitation $invitation): void
    {
        $user = (new ($this->teams->userModel()))->setConnection($this->teams->connection()->getName())
            ->newQuery()->whereRaw('LOWER(email) = ?', [$invitation->email])->first();
        if ($user) {
            $notification = Notification::make()
                ->title(__('filament-tenancy::teams.invited'))
                ->body(__('filament-tenancy::teams.email_body'))
                ->actions([Action::make('accept')->label(__('filament-tenancy::teams.accept'))->url($invitation->url())]);
            $this->teams->connection()->table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => DatabaseNotification::class,
                'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => (string) $user->getKey(),
                'data' => json_encode($notification->getDatabaseMessage(), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Mail::to($invitation->email)->send(new InvitationMail($invitation));
    }
}
