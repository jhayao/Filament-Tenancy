<?php

namespace Liern\FilamentTenancy\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Liern\FilamentTenancy\Models\WorkspaceDomain;

class WorkspaceDomainManager
{
    public function normalize(string $domain): string
    {
        $domain = trim(strtolower($domain));

        if ($domain === '' || str_contains($domain, '://') || str_contains($domain, '/') || str_contains($domain, '?') || str_contains($domain, '#') || str_contains($domain, '@')) {
            throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.invalid')]);
        }

        if (str_contains($domain, ':') || str_contains($domain, '*') || filter_var($domain, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.invalid')]);
        }

        $domain = rtrim($domain, '.');

        if (function_exists('idn_to_ascii')) {
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        if (strlen($domain) > 253 || ! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)) {
            throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.invalid')]);
        }

        $centralDomain = strtolower((string) config('filament-tenancy.central_domain'));
        $reservedSuffixes = array_filter([
            $centralDomain,
            ...config('filament-tenancy.custom_domains.reserved_suffixes', []),
        ]);

        foreach ($reservedSuffixes as $suffix) {
            $suffix = ltrim(strtolower((string) $suffix), '.');

            if ($domain === $suffix || str_ends_with($domain, '.'.$suffix)) {
                throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.reserved')]);
            }
        }

        return $domain;
    }

    public function add(int|string $workspaceId, string $domain): WorkspaceDomain
    {
        $domain = $this->normalize($domain);

        if (WorkspaceDomain::query()->where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.taken')]);
        }

        return WorkspaceDomain::query()->create([
            'workspace_id' => $workspaceId,
            'domain' => $domain,
            'verification_token' => bin2hex(random_bytes(32)),
        ]);
    }

    public function verify(WorkspaceDomain $domain, string $rateLimitKey): bool
    {
        $maxAttempts = (int) config('filament-tenancy.custom_domains.verification_attempts', 6);
        $decay = (int) config('filament-tenancy.custom_domains.verification_decay_seconds', 300);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            throw ValidationException::withMessages(['domain' => __('filament-tenancy::tenancy.domains.validation.rate_limited')]);
        }

        RateLimiter::hit($rateLimitKey, $decay);

        $records = @dns_get_record($domain->verificationRecordName(), defined('DNS_TXT') ? DNS_TXT : 16);
        $expected = $domain->verificationRecordValue();

        $verified = collect($records ?: [])->contains(function (array $record) use ($expected): bool {
            $value = $record['txt'] ?? $record['entries'][0] ?? null;

            if (is_array($value)) {
                $value = implode('', $value);
            }

            return hash_equals($expected, (string) $value);
        });

        if ($verified) {
            $domain->forceFill(['verified_at' => now()])->save();
        }

        return $verified;
    }

    public function remove(WorkspaceDomain $domain): void
    {
        $domain->delete();
    }

}
