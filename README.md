# Filament Tenancy

A standalone Filament v5 Composer plugin for dedicated database workspaces.

This first version provides workspace onboarding, central users and memberships, Filament's searchable workspace switcher, queued database creation/migrations/optional seeding, a polling setup screen, and an operator retry command. Each workspace has its own database named `tenant_{slug}` and an auto-incrementing integer ID; tenant tables do not need `tenant_id`.

Uses public `stancl/tenancy` v3.10, not the reference plugin's private v4 dependency. Path routing keeps login and workspaces on one origin: `/admin/workspaces/acme`. Subdomains, custom domains, shared databases, database pools, billing, invitations, resource syncing, and automatic database deletion are not included.

## Requirements

- PHP 8.3+; Laravel 12 or 13; Filament 5 with Livewire 4.
- A new tenancy installation. This package owns Stancl's tenant model, database and queue bootstrappers, and lifecycle listeners; do not combine it with another tenancy provider or run `tenancy:install`.
- SQLite, MySQL/MariaDB, or PostgreSQL supported by Stancl. The integration suite runs against SQLite, MySQL and PostgreSQL. Verify credentials and database-creation privileges in your deployment.

## Install in an application

This repository is a package, not a runnable Laravel application, and is not published to Packagist. Install the latest tagged release from GitHub by adding the repository to your application's `composer.json`:

```bash
composer config repositories.filament-tenancy vcs https://github.com/jhayao/Filament-Tenancy.git
composer require liern/filament-tenancy:^0.1
```

To install the current development branch instead, use `dev-main`:

```bash
composer config repositories.filament-tenancy vcs https://github.com/jhayao/Filament-Tenancy.git
composer require liern/filament-tenancy:dev-main
```

Then publish the package configuration and migrations in the application:

```bash
php artisan vendor:publish --tag=filament-tenancy-config
php artisan vendor:publish --tag=filament-tenancy-migrations
php artisan migrate
mkdir -p database/migrations/tenant
```

Migrations are explicitly published, not automatically loaded in production. Keep users, memberships, sessions, jobs, failed jobs, and database cache tables in central migrations. Put only workspace business tables in `database/migrations/tenant`. Do not copy the central users migration there.

Set `TENANCY_CENTRAL_CONNECTION` to your named central connection (defaults to `DB_CONNECTION`). Fresh installs name databases with the `tenant_` prefix followed by the workspace slug; set `TENANCY_DATABASE_NAME_PREFIX` to change the prefix. Keep slugs stable after provisioning because the generated database name is stored with the workspace. SQLite creates workspace database files in the application's `database` directory; make it writable. MySQL/PostgreSQL credentials need database-creation privileges. To use a separate server/template connection, publish Stancl's config without its installer:

```bash
php artisan vendor:publish --provider='Stancl\Tenancy\TenancyServiceProvider' --tag=config
```

Set `tenancy.database.template_tenant_connection` to a configured connection name. The connection name `tenant` is reserved for the dynamic connection.

### User model

Add `HasTenants` and `HasWorkspaces` to your existing user model. Keep your application's existing `FilamentUser::canAccessPanel()` authorization:

```php
use Filament\Models\Contracts\HasTenants;
use Liern\FilamentTenancy\Concerns\HasWorkspaces;

class User extends Authenticatable implements HasTenants // retain other existing interfaces
{
    use HasWorkspaces; // retain other existing traits
}
```

The trait pins the user model to the central connection and implements membership checks. Set `filament-tenancy.user_model` when your user model isn't `App\Models\User`. The membership table supports numeric, UUID and ULID user keys. If users are deleted, your application should remove their `workspace_user` rows; the package does not add a foreign key to an application-owned user table.

### Panel and resources

In the existing authenticated panel provider:

```php
use Liern\FilamentTenancy\TenancyPlugin;

return $panel
    // Keep your normal id, path, login, middleware, resources and pages.
    ->plugin(TenancyPlugin::make());
```

Tenant resources must extend the provided base class:

```php
use Liern\FilamentTenancy\Resources\TenantResource;

class ProjectResource extends TenantResource
{
    // Your normal Filament v5 resource definition.
}
```

Alternatively declare `protected static bool $isScopedToTenant = false;` on each dedicated resource. The plugin checks this because Filament's default relationship scoping expects a tenant relationship, which dedicated database models do not have. Do not reuse these resource classes in a shared-database tenant panel. Business models should use Laravel's default connection (do not hardcode the central connection). Keep central administration in a separate panel.

Visit `/admin` (or your panel's configured path), sign in, and create a workspace. The switcher also lets existing users create additional workspaces. Filament's tenant creation policy is respected; define a policy for `Liern\FilamentTenancy\Models\Tenant` to restrict signup.

### Panel options

The published configuration supplies application defaults. Fluent calls override them for that panel only:

```php
TenancyPlugin::make()
    ->routePrefix('teams')
    ->withTenantRegistration(App\Filament\Pages\RegisterOrganization::class)
    ->withTenantMenu()
    ->withTenantSwitcher()
    ->searchableTenantMenu()
    ->extraTenantMiddleware([App\Http\Middleware\AuditTenantAccess::class]);
```

| Configuration key | Fluent method | Default |
| --- | --- | --- |
| `route_prefix` | `routePrefix('teams')` | `workspaces` |
| `registration_page` | `withTenantRegistration(Page::class)` | `RegisterWorkspace::class` |
| `menu.enabled` | `withTenantMenu(false)` | `true` |
| `menu.switcher_enabled` | `withTenantSwitcher(false)` | `true` |
| `menu.searchable` | `searchableTenantMenu(false)` | `true` |
| `extra_tenant_middleware` | `extraTenantMiddleware([...])` | `[]` |

Use `withTenantRegistration(null)` (or `registration_page => null`) to remove self-service registration and its menu entry. Existing memberships remain usable. A replacement registration page must extend Filament's `RegisterTenant`; extend this package's `RegisterWorkspace` to retain its provisioning behavior. Route prefixes must be a single lowercase URL segment, such as `teams` or `client-workspaces`.

Additional middleware runs after membership/readiness checks and is persistent on Livewire updates. The setup page also runs this middleware, but remains in the central context; middleware should handle that case. Fluent middleware arrays replace the configured list. Hiding the menu or switcher changes navigation only, not authorization.

### Custom tenant model

Set the application-wide `tenant_model` configuration to a concrete subclass of the package model:

```php
namespace App\Models;

class Organization extends \Liern\FilamentTenancy\Models\Tenant
{
    // Add application-specific behavior here.
}
```

```php
// config/filament-tenancy.php
 'tenant_model' => App\Models\Organization::class,
```

The same model is used by Filament, Stancl, memberships, validation, provisioning, retries and the setup page. It is configured globally so workers do not depend on a panel being booted. Keep the inherited `workspaces` / `workspace_user` schema, integer workspace IDs, status cast and central connection behavior. Custom tables and alternate key layouts are outside this extension contract. Register authorization policies for your configured model.

Existing published configuration files can omit the new keys: defaults preserve `/workspaces/{slug}`, the existing registration page and searchable switcher. No schema migration is required. PostgreSQL membership reads explicitly cast application-owned user keys to text to match the existing string membership keys.

### Queue and retries

Use an asynchronous queue for production:

```dotenv
TENANCY_QUEUE_CONNECTION=database
```

```bash
php artisan queue:work database --queue=tenant-provisioning --timeout=300
php artisan workspaces:retry WORKSPACE_ID
```

Create the normal Laravel queue tables centrally if using the database queue. Use a shared cache driver that supports atomic locks for multiple workers. Set the queue's `retry_after` (or visibility timeout) above the job's 300-second timeout and the lock's 360-second expiry, for example 420 seconds. The job retries up to three times, with 30/120-second backoff. Synchronous queues work for local use but complete provisioning during signup instead of in the background.

Retries reuse the existing database and run outstanding migrations. Ready workspaces are no-ops. The retry command also recovers a workspace left in provisioning after a killed worker; overlapping queued attempts are serialized by a cache lock. Configure `filament-tenancy.seeder` only with a tenant-safe, idempotent seeder: a failed seeder can run again. Database deletion is deliberately not coupled to model deletion.

Setup failures remain closed to business resources. The browser shows a generic failure message; exceptions go through Laravel's normal worker logging/failed-job handling, without showing credentials or raw SQL errors to members. Requeue pending workspaces if queue dispatch was interrupted after the central transaction committed.

## Isolation boundaries

HTTP middleware checks current membership and readiness before switching connections, including Livewire updates. An outer middleware resets context after requests, even on exceptions. A Livewire batch cannot mix workspaces. Central users, workspace records, and default database-backed sessions/cache/queues stay on the central connection. If you configure custom stores/connections, pin those explicitly as well.

Only database and queue tenancy are enabled. Cache keys, files/uploads, Redis, external services and custom routes are not automatically isolated. Use workspace-specific keys/paths and authorization for those features. Do not query business tables before tenant middleware, or expose them through unprotected routes. Streaming responses, Octane/concurrent request runtimes, and custom Livewire endpoints are not verified in this release. Use the standard Laravel HTTP lifecycle and Livewire endpoint.

For scripts and custom jobs, explicitly initialize tenancy and always end it in a `finally` block. Do not run arbitrary tenant queries from central pages. Resource model authorization policies remain your application's responsibility.

## Customize and test

Publish the setup view using `php artisan vendor:publish --tag=filament-tenancy-views`. UI strings are in `resources/lang/en/tenancy.php`; Laravel translation overrides can replace them.

In this package repository:

```bash
composer install
composer test
vendor/bin/pint --test
```

The tests exercise provisioning, owner membership, input validation, failed setup/retry, separate tenant databases, Filament onboarding, access denial, HTTP resource rendering, Livewire isolation/revocation, custom models, panel options, real database queues, central sessions and context cleanup.

Run integration tests only against a disposable central database: the suite resets its tables and creates/deletes tenant databases. The configured user needs database creation/deletion privileges.

```bash
TENANCY_TEST_DB=mysql TENANCY_TEST_PORT=3306 TENANCY_TEST_DATABASE=tenancy_test TENANCY_TEST_USERNAME=root TENANCY_TEST_PASSWORD=tenancy-test composer test
TENANCY_TEST_DB=pgsql TENANCY_TEST_PORT=5432 TENANCY_TEST_DATABASE=tenancy_test TENANCY_TEST_USERNAME=postgres TENANCY_TEST_PASSWORD=tenancy-test composer test
```

`TENANCY_TEST_HOST` defaults to `127.0.0.1`. With no database selection, the suite uses temporary SQLite databases. CI runs both supported PHP/Laravel combinations against all three database drivers.

The browser smoke test starts a temporary Testbench host and SQLite databases, signs in, creates two workspaces using a real queue worker, waits for setup polling, and switches between them. It removes its temporary data afterward.

```bash
npm ci
npx playwright install --with-deps chromium
npm run test:browser
```

Set `TENANCY_BROWSER_PORT` if the default port `8765` is occupied.
