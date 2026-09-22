<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('filament-tenancy::teams.members') }}</x-slot>
        @if ($this->manager() && app(\Liern\FilamentTenancy\Teams\Teams::class)->full($this->team()))
            <p role="status">{{ __('filament-tenancy::teams.errors.full') }}</p>
        @endif
        <div class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->roster() as $member)
                <div class="flex flex-wrap items-center justify-between gap-4 py-4" wire:key="member-{{ $member->getKey() }}">
                    <div><p class="font-medium">{{ $member->name }}</p><p>{{ $member->email }}</p><p>{{ app(\Liern\FilamentTenancy\Teams\Teams::class)->role($this->team(), $member) }}</p></div>
                    <div class="flex flex-wrap gap-2">
                        @if ($this->mayManage($member))
                            {{ ($this->roleAction)(['user' => (string) $member->getKey()]) }}
                            {{ ($this->removeAction)(['user' => (string) $member->getKey()]) }}
                        @endif
                        @if ($this->owner() && (string) $member->getKey() !== (string) filament()->auth()->id())
                            {{ ($this->transferAction)(['user' => (string) $member->getKey()]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
    @if ($this->manager())
        <x-filament::section>
            <x-slot name="heading">{{ __('filament-tenancy::teams.invitations') }}</x-slot>
            @forelse ($this->invitations() as $invitation)
                <div class="flex flex-wrap justify-between gap-4 py-4" wire:key="invitation-{{ $invitation->id }}">
                    <div><p>{{ $invitation->email ?? __('filament-tenancy::teams.shareable_link') }} · {{ $invitation->role }}</p>
                        <p>{{ __('filament-tenancy::teams.email_expiry', ['date' => $invitation->expires_at->toDateTimeString()]) }}</p>
                        <p>{{ __('filament-tenancy::teams.uses', ['used' => $invitation->uses, 'limit' => $invitation->use_limit]) }}</p>
                        <p>{{ __('filament-tenancy::teams.'.($invitation->isActive() ? 'active' : 'inactive')) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
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
                <p>{{ __('filament-tenancy::teams.no_invitations') }}</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>
