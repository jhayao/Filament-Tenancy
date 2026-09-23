---
name: lona-tenancy-development
description: Build, configure, debug, and test Lona Tenancy integrations in Laravel and Filament applications, including workspace isolation, provisioning, teams, Shield, custom domains, and tenant resources.
---

# Lona Tenancy Development

Use this skill when changing a Laravel application that installs
`jhayao/lona-tenancy`, or when changing this package's integration behavior.

## Start with the host application's conventions

Before editing, inspect the host application's published
`config/filament-tenancy.php` and `config/teams.php`, panel provider, user model,
tenant model, migration layout, queue configuration, and existing policies. Preserve
the host application's authentication and panel configuration. Prefer the package's
fluent `TenancyPlugin` methods for panel-specific overrides and published config for
application-wide defaults.

For a new integration, install and publish the package resources:

```bash
composer require jhayao/lona-tenancy
php artisan vendor:publish --tag=filament-tenancy-config
php artisan vendor:publish --tag=filament-tenancy-migrations
php artisan migrate
mkdir -p database/migrations/tenant
```

Do not run `tenancy:install`; Lona Tenancy configures Stancl Tenancy itself.

## Choose the isolation model deliberately

- `database_strategy: dedicated` creates a database named from the stable workspace slug and uses the dynamic `tenant` connection. Tenant business models must use the default dynamic connection and must not hardcode the central connection.
- `database_strategy: shared` keeps business rows together and scopes `TenantResource` queries to the workspace relationship. Set `scope_resources_to_tenant` explicitly when overriding the strategy default.
- Keep users, workspaces, memberships, sessions, jobs, cache locks, notifications, and permissions central. Put only workspace business tables in `database/migrations/tenant`.
- Extend `Liern\FilamentTenancy\Resources\TenantResource` for tenant business resources. Keep central administration resources in a separate panel.

Initialize tenancy before custom tenant queries and restore the previous context in
a `finally` block. This applies to scripts, listeners, queued jobs, and console
commands. Do not expose tenant routes or business data before the package's tenant
middleware has checked membership and readiness.

## Configure Filament and users

The central user model should implement Filament's `HasTenants` contract and use
`Liern\FilamentTenancy\Concerns\HasWorkspaces`:

```php
use Filament\Models\Contracts\HasTenants;
use Liern\FilamentTenancy\Concerns\HasWorkspaces;

class User extends Authenticatable implements HasTenants
{
    use HasWorkspaces;
}
```

Register `TenancyPlugin::make()` on the existing authenticated panel. Use
`withTenantRegistration()`, `withTenantMenu()`, `withTenantSwitcher()`,
`searchableTenantMenu()`, `withTenantProfile()`, `customDomains()`,
`withTenantBilling()`, `databasePool()`, and `syncResources()` for panel-specific
behavior. A replacement registration page should extend the package's
`RegisterWorkspace` when it must retain provisioning behavior.

## Teams and authorization

Enable teams with `teams.enabled` or `TenancyPlugin::make()->withMembers()`. Configure
role definitions, `manager_roles`, `manager_assignable_roles`, seat limits, and
invitation expiry in `config/teams.php`. Use the `Teams` and `Invitations` services
for mutations instead of writing membership rows directly:

```php
$teams = app(\Liern\FilamentTenancy\Teams\Teams::class);
$teams->addMember($actor, $team, $user, 'member');
$teams->changeRole($actor, $team, $user, 'manager');
$teams->transferOwnership($owner, $team, $recipient);

app(\Liern\FilamentTenancy\Teams\Invitations::class)
    ->inviteMany($actor, $team, ['user@example.com'], 'member');
```

Enforce access through service authorization, `team.can:permission` middleware, or
the `teamcan` Blade conditional. Do not rely on hidden navigation or host Gate
overrides to bypass ownership, membership, or seat-limit rules. `owner` is reserved
and cannot be invited or assigned by ordinary role changes.

## Filament Shield integration

Shield is installed automatically but remains opt-in. Before enabling it:

1. Configure Spatie Permission team mode before running its central migrations.
2. Set `filament-shield.tenant_model` to the configured tenant model.
3. Add Spatie's `HasRoles` behavior to the authentication model.
4. Keep role and permission models on the central connection, including for custom models.
5. Register both Filament Shield and Lona Tenancy plugins on the panel.

Enable workspace role seeding with both `teams.shield.enabled` and
`teams.shield.seeding.enabled`. The configured `teams.roles` keys become Shield role
names; permissions are global central records and role rows are workspace-specific.
Seeding is idempotent, never removes unrelated roles, and rejects `owner`, Shield
super-admin, and excluded role names. Repair existing workspaces with:

```bash
php artisan workspaces:seed-roles WORKSPACE_ID
php artisan workspaces:seed-roles --all
```

Shield role seeding is independent of `filament-tenancy.seeder`, which seeds
application tables inside dedicated tenant databases.

## Provisioning, queues, and operations

Production provisioning should use an asynchronous queue:

```dotenv
TENANCY_QUEUE_CONNECTION=database
```

Run the worker with a 300-second timeout and configure `retry_after` above both the
job timeout and the 360-second overlap lock. Provisioning retries are safe and
re-run outstanding migrations; a ready workspace is a no-op. Use
`workspaces:retry WORKSPACE_ID` after a failed or interrupted setup.

Database pools are opt-in and require ordinary named connections. Resource syncing
is also opt-in and requires valid central-to-tenant model pairs implementing the
Stancl sync contracts. Validate these configurations at boot rather than silently
falling back to a different connection.

## Custom domains and authentication

Custom-domain authentication uses the central host and a short-lived, one-use
handoff bound to the verified workspace, panel, guard, user, target host, and browser
network/user-agent state. Do not share a parent `SESSION_DOMAIN` with customer
domains. Keep HTTPS and trusted-proxy configuration correct. The package does not
register DNS, route domains through Laravel Cloud or Cloudflare, or issue TLS
certificates.

## Migrations and testing

Publish the additive central team and notification migrations before enabling those
features. Existing PostgreSQL notification tables may require the package's explicit
repair migration; never recreate or delete the notification table to apply the fix.

For package changes, run:

```bash
composer test
vendor/bin/pint --test
```

Run MySQL and PostgreSQL integration tests only against disposable databases with
database creation/deletion privileges. Browser smoke tests require `npm ci`,
Playwright Chromium, and the temporary Testbench host. When modifying tenancy
context or Livewire behavior, add coverage for context reset, cross-workspace
access denial, queued provisioning retry, and exception cleanup.
