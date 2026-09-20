<?php

namespace Liern\FilamentTenancy;

use Filament\Contracts\Plugin;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Panel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Http\Middleware\WorkspaceHandoffMiddleware;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Pages\WorkspaceBilling;
use Liern\FilamentTenancy\Pages\WorkspaceDomains;
use Liern\FilamentTenancy\Resources\TenantResource;
use Liern\FilamentTenancy\Support\TenantModel;
use LogicException;
use ReflectionClass;

class TenancyPlugin implements Plugin
{
    protected array $overrides = [];

    public function routePrefix(string $prefix): static
    {
        $this->overrides['route_prefix'] = $prefix;

        return $this;
    }

    public function withTenantRegistration(?string $page = RegisterWorkspace::class): static
    {
        $this->overrides['registration_page'] = $page;

        return $this;
    }

    public function withTenantMenu(bool $enabled = true): static
    {
        $this->overrides['menu.enabled'] = $enabled;

        return $this;
    }

    public function withTenantSwitcher(bool $enabled = true): static
    {
        $this->overrides['menu.switcher_enabled'] = $enabled;

        return $this;
    }

    public function searchableTenantMenu(bool $enabled = true): static
    {
        $this->overrides['menu.searchable'] = $enabled;

        return $this;
    }

    public function withTenantProfile(bool $enabled = true, ?string $page = null): static
    {
        $this->overrides['profile.enabled'] = $enabled;
        $this->overrides['profile.page'] = $page ?? $this->option('profile.page');

        return $this;
    }

    public function customDomains(bool $enabled = true, ?string $page = null): static
    {
        $this->overrides['custom_domains.enabled'] = $enabled;
        $this->overrides['custom_domains.page'] = $page ?? $this->option('custom_domains.page');

        return $this;
    }

    public function extraTenantMiddleware(array $middleware): static
    {
        $this->overrides['extra_tenant_middleware'] = $middleware;

        return $this;
    }

    protected function option(string $key): mixed
    {
        return array_key_exists($key, $this->overrides)
            ? $this->overrides[$key]
            : config('filament-tenancy.'.$key);
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'liern-tenancy';
    }

    public function register(Panel $panel): void
    {
        $prefix = $this->option('route_prefix');
        if (! is_string($prefix) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $prefix)) {
            throw new LogicException('The workspace route prefix must be a non-empty lowercase URL segment.');
        }

        $registration = $this->option('registration_page');
        if ($registration !== null && (! is_string($registration) || ! is_subclass_of($registration, RegisterTenant::class) || (new ReflectionClass($registration))->isAbstract())) {
            throw new LogicException('The workspace registration page must extend '.RegisterTenant::class.' or be null.');
        }

        $customDomainsEnabled = (bool) $this->option('custom_domains.enabled');

        if ($customDomainsEnabled && $this->option('identification') !== 'subdomain') {
            throw new LogicException('Custom workspace domains require subdomain identification.');
        }

        $panel
            ->tenant(TenantModel::get(), slugAttribute: 'slug')
            ->tenantRegistration($registration)
            ->tenantMenu($this->option('menu.enabled'))
            ->tenantSwitcher($this->option('menu.switcher_enabled'))
            ->searchableTenantMenu($this->option('menu.searchable'))
            ->pages([Provisioning::class])
            ->tenantMiddleware([InitializeWorkspace::class, ...$this->option('extra_tenant_middleware')], isPersistent: true);

        if ($customDomainsEnabled) {
            $panel->middleware([WorkspaceHandoffMiddleware::class], isPersistent: true);
        }

        if ($this->option('identification') === 'subdomain') {
            if ($customDomainsEnabled) {
                $panel
                    ->tenantDomain('{tenant:*}')
                    ->resolveTenantUsing(function (string $key) {
                        $tenant = app(TenantModel::get())->resolveRouteBinding($key, 'slug');

                        if ($tenant !== null) {
                            return $tenant;
                        }

                        throw (new ModelNotFoundException)->setModel(TenantModel::get(), [$key]);
                    });
            } else {
                $panel->tenantDomain('{tenant:slug}.'.$this->option('central_domain'));
            }
        } else {
            $panel->tenantRoutePrefix($prefix);
        }

        if ($this->option('profile.enabled') && $this->option('profile.page')) {
            $panel->tenantProfile($this->option('profile.page'));
        }

        if ($this->option('billing.enabled')) {
            $panel->pages([WorkspaceBilling::class]);
        }

        if ($customDomainsEnabled) {
            $domainPage = $this->option('custom_domains.page');

            if (! is_string($domainPage) || ! is_subclass_of($domainPage, WorkspaceDomains::class)) {
                throw new LogicException('The custom domains page must extend '.WorkspaceDomains::class.'.');
            }

            $panel->pages([$domainPage]);
        }
    }

    public function boot(Panel $panel): void
    {
        // Never mutate Resource's inherited static flag: it can affect other panels.
        foreach ($panel->getResources() as $resource) {
            if ($resource::isScopedToTenant() && ! is_subclass_of($resource, TenantResource::class)) {
                throw new LogicException("{$resource} must extend Liern\\FilamentTenancy\\Resources\\TenantResource or declare protected static bool \$isScopedToTenant = false.");
            }
        }
    }
}
