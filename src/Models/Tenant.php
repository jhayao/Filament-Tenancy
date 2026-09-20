<?php

namespace Liern\FilamentTenancy\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Relations\WorkspaceUsers;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements HasName, TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $table = 'workspaces';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $casts = ['status' => ProvisioningStatus::class];

    public function getIncrementing(): bool
    {
        return true;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'status', 'created_at', 'updated_at'];
    }

    public function users(): BelongsToMany
    {
        $user = $this->newRelatedInstance(config('filament-tenancy.user_model'));

        return (new WorkspaceUsers($user->newQuery(), $this, 'workspace_user', 'workspace_id', 'user_id', $this->getKeyName(), $user->getKeyName(), 'users'))
            ->withPivot('is_owner')->withTimestamps();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getRouteKey()
    {
        if (config('filament-tenancy.identification') !== 'subdomain' || ! config('filament-tenancy.custom_domains.enabled')) {
            return parent::getRouteKey();
        }

        $requestHost = strtolower((string) request()->getHost());

        if ($requestHost !== '' && WorkspaceDomain::query()
            ->where('workspace_id', $this->getKey())
            ->where('domain', $requestHost)
            ->whereNotNull('verified_at')
            ->exists()) {
            return $requestHost;
        }

        $centralDomain = strtolower((string) config('filament-tenancy.central_domain'));

        return Str::finish((string) $this->getAttribute('slug'), '.'.$centralDomain);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (config('filament-tenancy.identification') !== 'subdomain' || ! config('filament-tenancy.custom_domains.enabled')) {
            return parent::resolveRouteBinding($value, $field);
        }

        $host = strtolower(rtrim((string) $value, '.'));
        $centralDomain = strtolower((string) config('filament-tenancy.central_domain'));

        if ($host === '' || $host === $centralDomain) {
            return null;
        }

        if (Str::endsWith($host, '.'.$centralDomain)) {
            return static::query()->where('slug', Str::beforeLast($host, '.'.$centralDomain))->first();
        }

        return WorkspaceDomain::query()
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->with('workspace')
            ->first()?->workspace;
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->getAttribute('logo_path');

        if (blank($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk(config('filament-tenancy.profile.logo_disk', 'public'))->url($path);
    }
}
