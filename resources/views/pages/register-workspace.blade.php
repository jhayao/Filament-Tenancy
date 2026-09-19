<div class="lw-registration-page">
    @include('filament-tenancy::partials.standalone-page-styles')

    <div class="lw-registration-content">
        <div class="lw-registration-copy">
            <h1 class="lw-page-heading">{{ __('filament-tenancy::tenancy.create_heading') }}</h1>
            <p class="lw-page-supporting-text">{{ __('filament-tenancy::tenancy.create_help') }}</p>
        </div>

        <div class="lw-registration-form">
            {{ $this->content }}
        </div>
    </div>
</div>
