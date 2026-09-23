<x-filament-panels::page>
    @include('filament-tenancy::partials.members-styles')
    @php
        $teams = app(\Liern\FilamentTenancy\Teams\Teams::class);
        $roster = $this->roster();
        $isManager = $this->manager();
        $invitations = $isManager ? $this->invitations() : collect();
    @endphp

    <div class="fi-tenancy-members">
        {{ $this->summary }}

        @if ($isManager && $teams->full($this->team()))
            <x-filament::callout color="warning" role="status"
                :heading="__('filament-tenancy::teams.seat_limit_reached')"
                :description="__('filament-tenancy::teams.errors.full')"
            />
        @endif

        <x-filament::section
            :heading="__('filament-tenancy::teams.members')"
            :description="__('filament-tenancy::teams.members_help')"
        >
            @forelse ($roster as $member)
                @php $role = $teams->role($this->team(), $member); @endphp
                <div class="fi-tenancy-members-row" wire:key="member-{{ $member->getKey() }}">
                    <div class="fi-tenancy-members-identity">
                        <x-filament-panels::avatar.user :user="$member" size="lg" />
                        <div class="fi-tenancy-members-details">
                            <div class="fi-tenancy-members-labels">
                                <p class="fi-tenancy-members-name">{{ $member->name }}</p>
                                @if (filament()->auth()->user()?->is($member))
                                    <x-filament::badge color="gray">{{ __('filament-tenancy::teams.you') }}</x-filament::badge>
                                @endif
                            </div>
                            <p class="fi-tenancy-members-meta">{{ $member->email }}</p>
                        </div>
                    </div>
                    <div class="fi-tenancy-members-actions">
                        <x-filament::badge :color="$role === 'owner' ? 'warning' : ($teams->isManagerRole($role) ? 'info' : 'gray')">{{ $role }}</x-filament::badge>
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
                    <div class="fi-tenancy-members-row" wire:key="invitation-{{ $invitation->id }}">
                        <div class="fi-tenancy-members-identity">
                            <div class="fi-tenancy-members-details">
                                <p class="fi-tenancy-members-name">{{ $invitation->email ?? __('filament-tenancy::teams.shareable_link') }}</p>
                                <div class="fi-tenancy-members-labels">
                                    <x-filament::badge color="gray">{{ $invitation->role }}</x-filament::badge>
                                    <x-filament::badge :color="$invitation->isActive() ? 'success' : 'gray'">{{ __('filament-tenancy::teams.'.($invitation->isActive() ? 'active' : 'inactive')) }}</x-filament::badge>
                                </div>
                                <p class="fi-tenancy-members-meta">{{ __('filament-tenancy::teams.email_expiry', ['date' => $invitation->expires_at->toDateTimeString()]) }} · {{ __('filament-tenancy::teams.uses', ['used' => $invitation->uses, 'limit' => $invitation->use_limit]) }}</p>
                            </div>
                        </div>
                        <div class="fi-tenancy-members-actions">
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
