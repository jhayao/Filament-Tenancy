<?php

namespace Liern\FilamentTenancy\Teams;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Teams\Exceptions\TeamsConfigurationException;

class PersonalTeams
{
    public function handle(Registered $event): void
    {
        if (! config('teams.enabled') || ! config('teams.personal_teams')) {
            return;
        }
        $teams = app(Teams::class);
        $teams->connection()->transaction(function () use ($teams, $event) {
            // Lock the central user so simultaneous registration listeners serialize.
            $user = $teams->user($event->user->getKey());
            $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $query = $teams->connection()->table('team_personal_teams')->where('user_id', (string) $user->getKey());
            if ($query->exists()) {
                return;
            }
            if ($creator = config('teams.personal_team_creator')) {
                $team = app($creator)->create($user);
            } else {
                if (config('teams.external')) {
                    throw new TeamsConfigurationException('personal_creator');
                }
                $team = app(CreateWorkspace::class)->create($user, [
                    'name' => __('filament-tenancy::teams.personal_name', ['name' => $user->getAttribute('name')]),
                    'slug' => 'personal-'.strtolower((string) Str::ulid()),
                ]);
            }
            $teams->connection()->table('team_personal_teams')->insert(['user_id' => (string) $user->getKey(), 'team_id' => (string) $team->getKey()]);
        });
    }
}
