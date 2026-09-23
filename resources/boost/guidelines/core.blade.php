## Lona Tenancy

Lona Tenancy is a Composer package for Filament 5 applications that provides
workspace onboarding, central users and memberships, Filament tenant switching,
dedicated or shared database strategies, queued provisioning, custom domains,
profiles, database pools, billing hooks, resource syncing, and team permissions.

### Compatibility and ownership

- Target PHP 8.3+, Laravel 12 or 13, Filament 5, Livewire 4, and Stancl Tenancy 3.10.
- This package owns the Stancl tenant model, database and queue bootstrappers, and lifecycle listeners. Do not combine it with another tenancy provider or run `tenancy:install`.
- The package is installed from GitHub and is a library, not a standalone Laravel application.
- Preserve the host application's existing panel IDs, paths, authentication, middleware, models, and policies when integrating the package.

### Installation and migrations

Publish configuration and central migrations in the host application:

```bash
php artisan vendor:publish --tag=filament-tenancy-config
php artisan vendor:publish --tag=filament-tenancy-migrations
php artisan migrate
mkdir -p database/migrations/tenant
```

Migrations are explicitly published and are not automatically loaded in production
unless `filament-tenancy.run_migrations` is enabled. Do not publish and load the
same migrations through both mechanisms. Keep users, memberships, sessions, jobs,
failed jobs, database cache tables, notifications, and permission tables on the
central connection. Put workspace business tables in the host application's
`database/migrations/tenant` directory only.

### Panel and model integration

Add `HasTenants` and `HasWorkspaces` to the central user model, then register the
plugin on the existing authenticated Filament panel:

```php
use Filament\Models\Contracts\HasTenants;
use Liern\FilamentTenancy\Concerns\HasWorkspaces;
use Liern\FilamentTenancy\TenancyPlugin;

class User extends Authenticatable implements HasTenants
{
    use HasWorkspaces;
}

return $panel->plugin(TenancyPlugin::make());
```

Tenant business resources should extend `Liern\FilamentTenancy\Resources\TenantResource`.
For a dedicated database strategy, models use the dynamic tenant connection and
must not hardcode the central connection. For a shared database strategy, tenant
resources use the workspace relationship scope. Central administration belongs in
a separate panel. Hiding the tenant menu or switcher changes navigation only; it
does not authorize access.

### Central and tenant context

Central users, workspace records, memberships, sessions, queues, cache locks, and
permission data stay central. Database and queue tenancy are enabled; cache keys,
files, Redis, external services, and custom routes are not automatically isolated.
Do not query tenant business tables before tenant middleware has initialized the
workspace. In custom jobs and scripts, explicitly initialize tenancy and always end
it in a `finally` block. Never allow a Livewire request or batch to mix workspaces.

### Provisioning and operations

Use an asynchronous queue in production, with the queue `retry_after` above the
300-second provisioning timeout and 360-second overlap lock expiry. Provisioning is
idempotent and retries existing databases and outstanding migrations. Use:

```bash
php artisan queue:work database --timeout=300
php artisan workspaces:retry WORKSPACE_ID
php artisan tenants:pool
```

Configure `filament-tenancy.seeder` only with a tenant-safe, idempotent seeder.
Provisioning failures must remain closed to business resources and must not expose
credentials or raw SQL errors to members.

### Teams, invitations, and permissions

Enable the team feature with `teams.enabled` or `TenancyPlugin::make()->withMembers()`.
The built-in service APIs are central and enforce membership, role, ownership, and
seat-limit rules server-side; UI visibility is not authorization. `owner` is
reserved and can only be granted during initial owner setup or ownership transfer.
Use `team.can` middleware or the `teamcan` Blade conditional for route and view
checks after Filament has resolved the workspace.

Filament Shield is installed with the package but is opt-in at runtime. Configure
Spatie Permission team mode, the central role and permission models, and the tenant
model before using `withFilamentShield()`. Shield roles and permissions remain on
the central connection. Workspace role seeding is separate from tenant database
seeding and is idempotent:

```bash
php artisan workspaces:seed-roles WORKSPACE_ID
php artisan workspaces:seed-roles --all
```

The seeder must be enabled with both `teams.shield.enabled` and
`teams.shield.seeding.enabled`; it never removes unrelated host-owned roles or
permissions.

### Custom domains and isolation

Custom-domain handoff is host- and workspace-bound, short-lived, and one-use.
Keep authentication on the central application host, do not share a parent
`SESSION_DOMAIN` with customer domains, and keep the application behind HTTPS with
trusted proxies configured. Verify ownership before accepting a domain and do not
claim that the package registers DNS, routes a hostname, or issues certificates.

### Verification

For package changes, run:

```bash
composer test
vendor/bin/pint --test
```

Integration tests may reset central tables and create or delete tenant databases;
run them only against disposable databases. Browser tests require Node, Playwright,
and Chromium.
