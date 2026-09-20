<?php

namespace Liern\FilamentTenancy;

use Filament\Billing\Providers\Contracts\BillingProvider;
use Filament\Contracts\Plugin;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Panel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Liern\FilamentTenancy\Billing\NullBillingProvider;
use Liern\FilamentTenancy\Http\Middleware\InitializeWorkspace;
use Liern\FilamentTenancy\Http\Middleware\WorkspaceHandoffMiddleware;
use Liern\FilamentTenancy\Pages\Provisioning;
use Liern\FilamentTenancy\Pages\RegisterWorkspace;
use Liern\FilamentTenancy\Pages\WorkspaceDomains;
use Liern\FilamentTenancy\Resources\TenantResource;
use Liern\FilamentTenancy\Support\TenantModel;
use LogicException;
use ReflectionClass;
use Stancl\Tenancy\Events\SyncedResourceSaved;
use Stancl\Tenancy\Listeners\UpdateSyncedResource;

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

    public function tenantMenuItems(array $items): static
    {
        $this->overrides['menu.items'] = $items;

        return $this;
    }

    public function withTenantProfile(bool|string $enabled = true, ?string $page = null): static
    {
        if (is_string($enabled)) {
            $page = $enabled;
            $enabled = true;
        }

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

    public function handoffInterstitial(bool $enabled = true): static
    {
        $this->overrides['custom_domains.interstitial'] = $enabled;

        return $this;
    }

    public function withTenantBilling(?string $provider = null, string $routeSlug = 'billing', bool $required = false): static
    {
        $this->overrides['billing.enabled'] = true;
        $this->overrides['billing.provider'] = $provider ?? $this->option('billing.provider', NullBillingProvider::class);
        $this->overrides['billing.route_slug'] = $routeSlug;
        $this->overrides['billing.required'] = $required;

        return $this;
    }

    public function ownershipRelationship(?string $relationship): static
    {
        $this->overrides['ownership_relationship'] = $relationship;

        return $this;
    }

    public function scopeResourcesToTenant(bool $enabled = true): static
    {
        $this->overrides['scope_resources_to_tenant'] = $enabled;

        return $this;
    }

    public function identification(string $identification): static
    {
        $this->overrides['identification'] = $identification;

        return $this;
    }

    public function centralDomain(string $domain): static
    {
        $this->overrides['central_domain'] = $domain;

        return $this;
    }

    public function databaseStrategy(string $strategy): static
    {
        $this->overrides['database_strategy'] = $strategy;

        return $this;
    }

    public function databasePool(array $connections, string $strategy = 'least-tenants', array $weights = []): static
    {
        $this->overrides['database_pool'] = [
            'enabled' => $connections !== [],
            'connections' => $connections,
            'strategy' => $strategy,
            'weights' => $weights,
        ];

        return $this;
    }

    public function queueResourceSync(bool $enabled = true): static
    {
        $this->overrides['resource_syncing.queue'] = $enabled;

        return $this;
    }

    public function syncResources(array $pairs): static
    {
        $this->overrides['resource_syncing.enabled'] = true;
        $this->overrides['resource_syncing.pairs'] = $pairs;

        return $this;
    }

    public function cleanupOrphanedResourceMappings(bool|array $cleanup = true): static
    {
        $this->overrides['resource_syncing.cleanup'] = $cleanup;

        return $this;
    }

    public function extraTenantMiddleware(array $middleware): static
    {
        $this->overrides['extra_tenant_middleware'] = $middleware;

        return $this;
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->overrides)
            ? $this->overrides[$key]
            : config('filament-tenancy.'.$key, $default);
    }

    protected function registrationPage(): ?string
    {
        if (array_key_exists('registration_page', $this->overrides)) {
            return $this->overrides['registration_page'];
        }

        $registration = config('filament-tenancy.registration_page');
        $onboarding = config('filament-tenancy.onboarding', []);

        if (is_array($onboarding) && $registration === RegisterWorkspace::class) {
            if (($onboarding['enabled'] ?? true) === false) {
                return null;
            }

            return $onboarding['page'] ?? $registration;
        }

        return $registration;
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
        if (array_key_exists('database_strategy', $this->overrides)) {
            config(['filament-tenancy.database_strategy' => $this->overrides['database_strategy']]);
        }

        if (array_key_exists('database_pool', $this->overrides)) {
            config(['filament-tenancy.database_pool' => $this->overrides['database_pool']]);
        }

        foreach (['identification', 'central_domain', 'ownership_relationship', 'scope_resources_to_tenant'] as $key) {
            if (array_key_exists($key, $this->overrides)) {
                config(['filament-tenancy.'.$key => $this->overrides[$key]]);
            }
        }

        foreach (['custom_domains.enabled', 'custom_domains.page', 'custom_domains.interstitial', 'resource_syncing.enabled', 'resource_syncing.pairs', 'resource_syncing.queue', 'resource_syncing.cleanup'] as $key) {
            if (array_key_exists($key, $this->overrides)) {
                config(['filament-tenancy.'.$key => $this->overrides[$key]]);
            }
        }

        if ($this->option('resource_syncing.enabled') ?? false) {
            UpdateSyncedResource::$shouldQueue = (bool) $this->option('resource_syncing.queue');

            if (! Event::hasListeners(SyncedResourceSaved::class)) {
                Event::listen(SyncedResourceSaved::class, UpdateSyncedResource::class);
            }
        }

        $prefix = $this->option('route_prefix');
        if (! is_string($prefix) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $prefix)) {
            throw new LogicException('The workspace route prefix must be a non-empty lowercase URL segment.');
        }

        $identification = $this->option('identification');
        if (! in_array($identification, ['path', 'subdomain'], true)) {
            throw new LogicException("The workspace identification mode must be 'path' or 'subdomain'.");
        }

        $strategy = $this->option('database_strategy');
        if (! in_array($strategy, ['dedicated', 'shared'], true)) {
            throw new LogicException("The workspace database strategy must be 'dedicated' or 'shared'.");
        }

        $registration = $this->registrationPage();
        if ($registration !== null && (! is_string($registration) || ! is_subclass_of($registration, RegisterTenant::class) || (new ReflectionClass($registration))->isAbstract())) {
            throw new LogicException('The workspace registration page must extend '.RegisterTenant::class.' or be null.');
        }

        $customDomainsEnabled = (bool) $this->option('custom_domains.enabled');

        if ($customDomainsEnabled && $identification !== 'subdomain') {
            throw new LogicException('Custom workspace domains require subdomain identification.');
        }

        $billingEnabled = (bool) $this->option('billing.enabled');
        $billingProvider = null;
        if ($billingEnabled) {
            $provider = $this->option('billing.provider');
            $billingProvider = is_string($provider) ? app($provider) : $provider;

            if (! $billingProvider instanceof BillingProvider) {
                throw new LogicException('The workspace billing provider must implement '.BillingProvider::class.'.');
            }

            if ($this->option('billing.required') && blank($billingProvider->getSubscribedMiddleware())) {
                throw new LogicException('A required workspace billing provider must return subscribed middleware.');
            }
        }

        $panel
            ->tenant(TenantModel::get(), slugAttribute: 'slug', ownershipRelationship: $this->option('ownership_relationship'))
            ->tenantRegistration($registration)
            ->tenantMenu($this->option('menu.enabled'))
            ->tenantSwitcher($this->option('menu.switcher_enabled'))
            ->searchableTenantMenu($this->option('menu.searchable'))
            ->pages([Provisioning::class])
            ->tenantMiddleware([InitializeWorkspace::class, ...$this->extraMiddleware()], isPersistent: true);

        if ($items = $this->option('menu.items')) {
            $panel->tenantMenuItems($items);
        }

        if ($billingEnabled) {
            $panel
                ->tenantBillingProvider($billingProvider)
                ->tenantBillingRouteSlug((string) $this->option('billing.route_slug', 'billing'))
                ->requiresTenantSubscription((bool) $this->option('billing.required'));
        }

        if ($customDomainsEnabled) {
            $panel->middleware([WorkspaceHandoffMiddleware::class], isPersistent: true);
        }

        if ($identification === 'subdomain') {
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

        if ($customDomainsEnabled) {
            $domainPage = $this->option('custom_domains.page');

            if (! is_string($domainPage) || ! is_subclass_of($domainPage, WorkspaceDomains::class)) {
                throw new LogicException('The custom domains page must extend '.WorkspaceDomains::class.'.');
            }

            $panel->pages([$domainPage]);
        }
    }

    protected function extraMiddleware(): array
    {
        if (array_key_exists('extra_tenant_middleware', $this->overrides)) {
            return $this->overrides['extra_tenant_middleware'];
        }

        $legacy = config('filament-tenancy.extra_tenant_middleware', []);

        return $legacy !== [] ? $legacy : config('filament-tenancy.middleware.extra', []);
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
