<x-filament-panels::page>
    <x-filament::section>
        <div wire:poll.3s="checkStatus" role="status" aria-live="polite">
            <h2>{{ $workspaceName }}</h2>
            <p>{{ $status->getLabel() }}</p>
            @if ($status === \Liern\FilamentTenancy\Enums\ProvisioningStatus::Failed)
                <p>{{ __('filament-tenancy::tenancy.failed_help') }}</p>
            @else
                <p>{{ __('filament-tenancy::tenancy.wait_help') }}</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
