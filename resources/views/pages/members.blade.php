<x-filament-panels::page>
    @php
        $teams = app(\Liern\FilamentTenancy\Teams\Teams::class);
        $roster = $this->roster();
        $isManager = $this->manager();
        $invitations = $isManager ? $this->invitations() : collect();
        $activeInvitations = $invitations->filter(fn ($invitation) => $invitation->isActive());
        $seatLimit = $teams->limit($this->team());
        $seatUsage = $teams->seats($this->team());
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.member_count') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $roster->count() }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.member_count_help') }}</p>
            </x-filament::section>

            <x-filament::section>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.pending_invitations') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $isManager ? $activeInvitations->count() : '—' }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.pending_invitations_help') }}</p>
            </x-filament::section>

            <x-filament::section>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.seats') }}</p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ $seatUsage }}<span class="text-lg font-normal text-gray-500 dark:text-gray-400">{{ $seatLimit === null ? ' / ∞' : ' / '.$seatLimit }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.seats_help') }}</p>
            </x-filament::section>
        </div>

        @if ($isManager && $teams->full($this->team()))
            <x-filament::section class="border-warning-600/30 bg-warning-50 dark:border-warning-400/30 dark:bg-warning-950/20">
                <div role="status">
                    <p class="font-medium text-warning-800 dark:text-warning-200">{{ __('filament-tenancy::teams.seat_limit_reached') }}</p>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ __('filament-tenancy::teams.errors.full') }}</p>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section
            :heading="__('filament-tenancy::teams.members')"
            :description="__('filament-tenancy::teams.members_help')"
        >
            @forelse ($roster as $member)
                @php $role = $teams->role($this->team(), $member); @endphp
                <div class="flex flex-col gap-4 border-b border-gray-200 py-5 last:border-0 last:pb-0 first:pt-0 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between" wire:key="member-{{ $member->getKey() }}">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-filament-panels::avatar.user :user="$member" size="lg" />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium text-gray-950 dark:text-white">{{ $member->name }}</p>
                                @if (filament()->auth()->user()?->is($member))
                                    <x-filament::badge color="gray">{{ __('filament-tenancy::teams.you') }}</x-filament::badge>
                                @endif
                            </div>
                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                            <div class="mt-2">
                                <x-filament::badge :color="$role === 'owner' ? 'warning' : ($teams->isManagerRole($role) ? 'info' : 'gray')">{{ $role }}</x-filament::badge>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        @if ($this->mayImpersonate($member))
                            {{ ($this->impersonateAction)(['user' => (string) $member->getKey()]) }}
                        @endif
                        @if ($this->mayManage($member))
                            {{ ($this->roleAction)(['user' => (string) $member->getKey()]) }}
                            {{ ($this->removeAction)(['user' => (string) $member->getKey()]) }}
                        @endif
                        @if ($this->owner() && (string) $member->getKey() !== (string) filament()->auth()->id())
                            {{ ($this->transferAction)(['user' => (string) $member->getKey()]) }}
                        @endif
                    </div>
                </div>
            @empty
                <x-filament::empty-state
                    :heading="__('filament-tenancy::teams.no_members')"
                    :description="__('filament-tenancy::teams.no_members_help')"
                />
            @endforelse
        </x-filament::section>

        @if ($isManager)
            <x-filament::section
                :heading="__('filament-tenancy::teams.invitations')"
                :description="__('filament-tenancy::teams.invitations_help')"
            >
                @forelse ($invitations as $invitation)
                    <div class="flex flex-col gap-4 border-b border-gray-200 py-5 last:border-0 last:pb-0 first:pt-0 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between" wire:key="invitation-{{ $invitation->id }}">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                                {{ $invitation->email ? str($invitation->email)->substr(0, 1)->upper() : '#' }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-950 dark:text-white">{{ $invitation->email ?? __('filament-tenancy::teams.shareable_link') }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <x-filament::badge color="gray">{{ $invitation->role }}</x-filament::badge>
                                    <x-filament::badge :color="$invitation->isActive() ? 'success' : 'gray'">{{ __('filament-tenancy::teams.'.($invitation->isActive() ? 'active' : 'inactive')) }}</x-filament::badge>
                                </div>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('filament-tenancy::teams.email_expiry', ['date' => $invitation->expires_at->toDateTimeString()]) }} · {{ __('filament-tenancy::teams.uses', ['used' => $invitation->uses, 'limit' => $invitation->use_limit]) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            @if ($invitation->isActive())
                                <x-filament::button color="gray" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText(@js($invitation->url())).then(() => copied = true)">
                                    <span x-show="! copied">{{ __('filament-tenancy::teams.copy_link') }}</span><span x-show="copied" x-cloak>{{ __('filament-tenancy::teams.copied') }}</span>
                                </x-filament::button>
                            @endif
                            @if ($invitation->email && ! $invitation->revoked_at && ! $invitation->accepted_at)
                                {{ ($this->resendAction)(['invitation' => $invitation->id]) }}
                            @endif
                            @if (! $invitation->revoked_at && ! $invitation->accepted_at)
                                {{ ($this->revokeAction)(['invitation' => $invitation->id]) }}
                            @endif
                        </div>
                    </div>
                @empty
                    <x-filament::empty-state
                        :heading="__('filament-tenancy::teams.no_invitations')"
                        :description="__('filament-tenancy::teams.no_invitations_help')"
                    />
                @endforelse
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
