<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">{{ __('filament-tenancy::tenancy.domains.add_heading') }}</x-slot>
            <x-slot name="description">{{ __('filament-tenancy::tenancy.domains.add_help') }}</x-slot>

            <form wire:submit="addDomain" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit">
                    {{ __('filament-tenancy::tenancy.domains.add') }}
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('filament-tenancy::tenancy.domains.existing_heading') }}</x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($this->getDomains() as $domain)
                    <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $domain->domain }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $domain->isVerified() ? __('filament-tenancy::tenancy.domains.status.verified') : __('filament-tenancy::tenancy.domains.status.pending') }}
                            </p>
                            @unless ($domain->isVerified())
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ __('filament-tenancy::tenancy.domains.txt_instruction', ['name' => $domain->verificationRecordName(), 'value' => $domain->verificationRecordValue()]) }}
                                </p>
                                <p class="mt-1 break-all font-mono text-xs text-gray-500 dark:text-gray-400">
                                    {{ $domain->verificationRecordName() }} = {{ $domain->verificationRecordValue() }}
                                </p>
                            @endunless
                        </div>

                        <div class="flex shrink-0 gap-2">
                            @unless ($domain->isVerified())
                                <x-filament::button wire:click="verifyDomain({{ $domain->getKey() }})" color="gray">
                                    {{ __('filament-tenancy::tenancy.domains.verify') }}
                                </x-filament::button>
                            @endunless
                            <x-filament::button wire:click="removeDomain({{ $domain->getKey() }})" color="danger">
                                {{ __('filament-tenancy::tenancy.domains.remove') }}
                            </x-filament::button>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament-tenancy::tenancy.domains.empty') }}
                    </p>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
