<p>{{ __('filament-tenancy::teams.email_body') }}</p>
<p><a href="{{ $url }}">{{ __('filament-tenancy::teams.accept') }}</a></p>
<p>{{ __('filament-tenancy::teams.email_expiry', ['date' => $invitation->expires_at->toDateTimeString()]) }}</p>
