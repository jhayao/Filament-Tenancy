<div class="lw-provisioning-page">
    @include('filament-tenancy::partials.standalone-page-styles')

    @if ($status === \Liern\FilamentTenancy\Enums\ProvisioningStatus::Ready)
        <div class="lw-provisioning-state" role="status" aria-live="polite">
            <h1 class="lw-page-heading">{{ __('filament-tenancy::tenancy.ready_heading') }}</h1>
            <p class="lw-page-supporting-text">{{ __('filament-tenancy::tenancy.ready_help') }}</p>
            <a class="lw-primary-action" href="{{ $dashboardUrl }}">
                {{ __('filament-tenancy::tenancy.go_to_dashboard') }}
            </a>
        </div>
    @elseif ($status === \Liern\FilamentTenancy\Enums\ProvisioningStatus::Failed)
        <div class="lw-provisioning-state" role="status" aria-live="assertive">
            <h1 class="lw-page-heading">{{ __('filament-tenancy::tenancy.failed_heading') }}</h1>
            <p class="lw-page-supporting-text">{{ __('filament-tenancy::tenancy.failed_help') }}</p>
            <button class="lw-primary-action" type="button" wire:click="retryProvisioning">
                {{ __('filament-tenancy::tenancy.retry') }}
            </button>
            @if (filled($supportUrl))
                <p class="mt-4"><a class="lw-secondary-action" href="{{ $supportUrl }}">{{ __('filament-tenancy::tenancy.contact_support') }}</a></p>
            @endif
        </div>
    @else
        <div class="lw-provisioning-state" wire:poll.3s="checkStatus" role="status" aria-live="polite" aria-busy="true">
            <div class="lw-spinner" aria-hidden="true"></div>
            <span class="fi-sr-only">{{ $status->getLabel() }}</span>
            <h1 class="lw-page-heading">{{ __('filament-tenancy::tenancy.provisioning_heading', ['workspace' => $workspaceName]) }}</h1>
            <p class="lw-page-supporting-text">{{ __('filament-tenancy::tenancy.provisioning_help') }}</p>
        </div>
    @endif
</div>
