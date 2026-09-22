<?php

namespace Liern\FilamentTenancy\Teams;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Liern\FilamentTenancy\Relations\WorkspaceUsers;
use Liern\FilamentTenancy\Teams\Events\MemberAdded;
use Liern\FilamentTenancy\Teams\Events\MemberRemoved;
use Liern\FilamentTenancy\Teams\Events\MemberRoleChanged;
use Liern\FilamentTenancy\Teams\Events\OwnershipTransferred;
use Liern\FilamentTenancy\Teams\Exceptions\InvalidRoleException;
use Liern\FilamentTenancy\Teams\Exceptions\OwnershipException;
use Liern\FilamentTenancy\Teams\Exceptions\SeatLimitException;
use Liern\FilamentTenancy\Teams\Exceptions\TeamAuthorizationException;

class Teams
{
    public function registerNotificationRelation(): void
    {
        $class = $this->userModel();
        if (! method_exists($class, 'notifications') && ! (new $class)->relationResolver($class, 'notifications')) {
            $class::resolveRelationUsing('notifications', fn (Model $user) => $user->morphMany(DatabaseNotification::class, 'notifiable')->latest());
        }
    }

    public function connection(): Connection
    {
        return DB::connection(config('teams.connection') ?? config('filament-tenancy.central_connection'));
    }

    public function teamModel(): string
    {
        return config('teams.model') ?? config('filament-tenancy.tenant_model');
    }

    public function userModel(): string
    {
        return config('teams.user_model') ?? config('filament-tenancy.user_model');
    }

    public function team(string|int $id): Model
    {
        return (new ($this->teamModel()))->setConnection($this->connection()->getName())->newQuery()->findOrFail($id);
    }

    public function user(string|int $id): Model
    {
        return (new ($this->userModel()))->setConnection($this->connection()->getName())->newQuery()->findOrFail($id);
    }

    public function members(Model $team): BelongsToMany
    {
        $user = (new ($this->userModel()))->setConnection($this->connection()->getName());

        return (new WorkspaceUsers($user->newQuery(), $team, config('teams.pivot'), config('teams.team_key'), config('teams.user_key'), $team->getKeyName(), $user->getKeyName(), 'members'))
            ->withPivot(['role', 'managed_role_id', ...array_filter([config('teams.owner_column')])])->withTimestamps();
    }

    public function forUser(Model $user): BelongsToMany
    {
        $team = (new ($this->teamModel()))->setConnection($this->connection()->getName());

        return (new BelongsToMany($team->newQuery(), $user, config('teams.pivot'), config('teams.user_key'), config('teams.team_key'), $user->getKeyName(), $team->getKeyName(), 'teams'))
            ->withPivot(['role', ...array_filter([config('teams.owner_column')])])->withTimestamps();
    }

    public function memberships(Model $team): Builder
    {
        return $this->connection()->table(config('teams.pivot'))->where(config('teams.team_key'), $team->getKey());
    }

    public function membership(Model $team, Model $user): ?object
    {
        return $this->memberships($team)->where(config('teams.user_key'), (string) $user->getKey())->first();
    }

    public function role(Model $team, Model $user): ?string
    {
        $membership = $this->membership($team, $user);
        if (! $membership) {
            return null;
        }
        $owner = config('teams.owner_column');

        return $owner && ($membership->{$owner} ?? false) ? 'owner' : $membership->role;
    }

    public function guard(): string
    {
        return Filament::getCurrentPanel()?->getAuthGuard() ?? config('auth.defaults.guard', 'web');
    }

    public function isManagerRole(?string $role): bool
    {
        return $role === 'owner' || in_array($role, config('teams.manager_roles'), true);
    }

    public function roles(Model $team, ?Model $actor = null, ?string $guard = null): array
    {
        $roles = app(Shield::class)->enabled()
            ? app(Shield::class)->roles($team, $guard ?? $this->guard())
            : collect(config('teams.roles'))->mapWithKeys(fn ($definition, $key) => [$key => $definition['name']])->all();
        unset($roles['owner']);
        if ($actor && $this->role($team, $actor) !== 'owner') {
            $roles = array_intersect_key($roles, array_flip(config('teams.manager_assignable_roles')));
            foreach (array_keys($roles) as $role) {
                if ($this->isManagerRole($role)) {
                    unset($roles[$role]);
                }
            }
        }

        return $roles;
    }

    public function assertRole(Model $team, string $role, ?Model $actor = null, ?string $guard = null): void
    {
        if (! array_key_exists($role, $this->roles($team, $actor, $guard))) {
            throw new InvalidRoleException;
        }
    }

    public function authorizeManager(Model $team, Model $actor, ?Model $target = null): void
    {
        $role = $this->role($team, $actor);
        if (! $this->isManagerRole($role)
            || ($target && ($this->role($team, $target) === 'owner' || ($role !== 'owner' && $this->isManagerRole($this->role($team, $target)))))) {
            throw new TeamAuthorizationException;
        }
    }

    public function hasTeamPermission(Model $user, Model $team, string $permission): bool
    {
        $role = $this->role($team, $user);
        if ($role === null) {
            return false;
        }
        if (in_array($permission, ['members.view', 'members.leave'], true)) {
            return true;
        }
        if (in_array($permission, ['members.invite', 'members.update', 'members.remove'], true)) {
            return $this->isManagerRole($role);
        }
        if (in_array($permission, ['members.manage-managers', 'ownership.transfer'], true)) {
            return $role === 'owner';
        }
        if (app(Shield::class)->enabled()) {
            return app(Shield::class)->context($team, $user, fn () => $user->checkPermissionTo($permission, $this->guard()));
        }
        $permissions = config('teams.roles.'.$role.'.permissions', []);

        return $role === 'owner' || in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function limit(Model $team): ?int
    {
        $resolver = config('teams.seat_limit_resolver');
        $limit = $resolver ? app($resolver)->limit($team) : config('teams.seat_limit');

        return $limit === null ? null : max(0, (int) $limit);
    }

    public function seats(Model $team, ?int $exceptInvitation = null): int
    {
        return $this->memberships($team)->count() + Invitation::query()->where('team_id', (string) $team->getKey())
            ->whereNotNull('email')->whereNull('revoked_at')->whereNull('accepted_at')
            ->where('expires_at', '>', now())->whereColumn('uses', '<', 'use_limit')
            ->when($exceptInvitation, fn ($q) => $q->whereKeyNot($exceptInvitation))->count();
    }

    public function full(Model $team, ?int $exceptInvitation = null): bool
    {
        $limit = $this->limit($team);

        return $limit !== null && $this->seats($team, $exceptInvitation) >= $limit;
    }

    public function assertCapacity(Model $team, ?int $exceptInvitation = null): void
    {
        if ($this->full($team, $exceptInvitation)) {
            throw new SeatLimitException;
        }
    }

    public function locked(Model $team, Closure $callback): mixed
    {
        return $this->connection()->transaction(function () use ($team, $callback) {
            (new ($this->teamModel()))->setConnection($this->connection()->getName())->newQuery()->whereKey($team->getKey())->lockForUpdate()->firstOrFail();

            return $callback();
        }, 3);
    }

    public function emit(object $event): void
    {
        $this->connection()->afterCommit(fn () => event($event));
    }

    public function addMember(Model $actor, Model $team, Model $user, string $role, ?string $guard = null): void
    {
        $this->locked($team, function () use ($actor, $team, $user, $role, $guard) {
            $this->authorizeManager($team, $actor);
            $this->assertRole($team, $role, $actor, $guard);
            if ($this->membership($team, $user)) {
                return;
            }
            $reservation = app(Invitations::class)->active($team)
                ->where('email', strtolower((string) $user->getAttribute('email')))->first();
            $this->assertCapacity($team, $reservation?->id);
            $this->attach($team, $user, $role, $guard);
            $reservation?->update(['accepted_at' => now(), 'uses' => 1]);
        });
    }

    /** Bootstrap a newly created team; never grants another owner to an existing team. */
    public function initializeOwner(Model $team, Model $owner): void
    {
        $this->locked($team, function () use ($team, $owner) {
            if ($this->memberships($team)->exists()) {
                throw new OwnershipException;
            }
            $this->attach($team, $owner, 'owner');
        });
    }

    protected function attach(Model $team, Model $user, string $role, ?string $guard = null): void
    {
        $data = [config('teams.team_key') => $team->getKey(), config('teams.user_key') => (string) $user->getKey(), 'role' => $role, 'created_at' => now(), 'updated_at' => now()];
        if ($column = config('teams.owner_column')) {
            $data[$column] = $role === 'owner';
        }
        $data['managed_role_id'] = app(Shield::class)->sync($team, $user, $role, null, $guard ?? $this->guard());
        $this->connection()->table(config('teams.pivot'))->insert($data);
        $this->emit(new MemberAdded($team, $user, $role));
    }

    public function changeRole(Model $actor, Model $team, Model $user, string $role): void
    {
        $this->locked($team, function () use ($actor, $team, $user, $role) {
            $this->authorizeManager($team, $actor, $user);
            $this->assertRole($team, $role, $actor);
            if (! $this->membership($team, $user)) {
                throw new TeamAuthorizationException;
            }
            $this->writeRole($team, $user, $role);
        });
    }

    protected function writeRole(Model $team, Model $user, string $role): void
    {
        $previous = $this->membership($team, $user);
        $oldRole = $this->role($team, $user);
        $data = ['role' => $role, 'updated_at' => now(), 'managed_role_id' => app(Shield::class)->sync($team, $user, $role, $previous->managed_role_id, $this->guard())];
        if ($column = config('teams.owner_column')) {
            $data[$column] = $role === 'owner';
        }
        $this->memberships($team)->where(config('teams.user_key'), (string) $user->getKey())->update($data);
        $this->emit(new MemberRoleChanged($team, $user, $oldRole, $role));
    }

    public function removeMember(Model $actor, Model $team, Model $user): void
    {
        $this->locked($team, function () use ($actor, $team, $user) {
            if ((string) $actor->getKey() !== (string) $user->getKey()) {
                $this->authorizeManager($team, $actor, $user);
            }
            $membership = $this->membership($team, $user);
            if (! $membership) {
                throw new TeamAuthorizationException;
            }
            $ownerColumn = config('teams.owner_column');
            if ($this->role($team, $user) === 'owner' && $this->memberships($team)->where(fn ($q) => $q->where('role', 'owner')->when($ownerColumn, fn ($q) => $q->orWhere($ownerColumn, true)))->count() <= 1) {
                throw new OwnershipException;
            }
            app(Shield::class)->sync($team, $user, null, $membership->managed_role_id, $this->guard());
            $this->memberships($team)->where(config('teams.user_key'), (string) $user->getKey())->delete();
            $this->emit(new MemberRemoved($team, $user));
        });
    }

    public function transferOwnership(Model $actor, Model $team, Model $recipient): void
    {
        $this->locked($team, function () use ($actor, $team, $recipient) {
            if ($this->role($team, $actor) !== 'owner' || ! $this->membership($team, $recipient) || (string) $actor->getKey() === (string) $recipient->getKey()) {
                throw new TeamAuthorizationException;
            }
            $this->assertRole($team, 'manager');
            $this->writeRole($team, $recipient, 'owner');
            $this->writeRole($team, $actor, 'manager');
            $this->emit(new OwnershipTransferred($team, $actor, $recipient));
        });
    }
}
