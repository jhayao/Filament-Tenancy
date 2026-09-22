<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('filament-tenancy::teams.accept') }}</title></head>
<body><main>
<h1>{{ __('filament-tenancy::teams.join', ['team' => $team->getAttribute('name')]) }}</h1>
@if (session('teams.error'))<p role="alert">{{ session('teams.error') }}</p>@endif
<form method="post" action="{{ $invitation->url() }}">@csrf<button type="submit">{{ __('filament-tenancy::teams.accept') }}</button></form>
</main></body></html>
