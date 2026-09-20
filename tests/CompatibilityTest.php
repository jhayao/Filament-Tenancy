<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Panel;
use Illuminate\Http\Request;
use Liern\FilamentTenancy\Billing\NullBillingProvider;
use Liern\FilamentTenancy\Http\Middleware\ManageWorkspaceSessionCookie;
use Liern\FilamentTenancy\Support\DatabasePoolAllocator;
use Liern\FilamentTenancy\TenancyPlugin;
use LogicException;

class CompatibilityTest extends TestCase
{
    public function test_extended_panel_configuration_is_forwarded_to_filament(): void
    {
        $panel = Panel::make()->id('compatibility')->plugin(TenancyPlugin::make()
            ->ownershipRelationship('organization')
            ->withTenantBilling()
            ->tenantMenuItems([]));

        $this->assertSame('organization', $panel->getTenantOwnershipRelationshipName());
        $this->assertTrue($panel->hasTenantBilling());
        $this->assertSame('/billing', $panel->getTenantBillingRouteSlug());
        $this->assertInstanceOf(NullBillingProvider::class, $panel->getTenantBillingProvider());
    }

    public function test_required_null_billing_provider_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('required workspace billing provider');

        Panel::make()->id('required-billing')->plugin(TenancyPlugin::make()->withTenantBilling(null, 'billing', true));
    }

    public function test_database_pool_rejects_unknown_connections(): void
    {
        config(['filament-tenancy.database_pool' => [
            'enabled' => true,
            'connections' => ['missing_pool_connection'],
            'strategy' => 'least-tenants',
        ]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing_pool_connection');

        app(DatabasePoolAllocator::class)->validate();
    }

    public function test_session_cookie_management_shares_only_central_subdomains(): void
    {
        config([
            'filament-tenancy.manage_session_cookie' => true,
            'filament-tenancy.identification' => 'subdomain',
            'filament-tenancy.central_domain' => 'example.test',
            'session.domain' => 'original.test',
        ]);

        $middleware = app(ManageWorkspaceSessionCookie::class);
        $tenantRequest = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'acme.example.test']);
        $tenantResponse = $middleware->handle($tenantRequest, fn () => response(config('session.domain')));
        $this->assertSame('.example.test', $tenantResponse->getContent());
        $this->assertSame('original.test', config('session.domain'));

        $customRequest = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'app.customer.test']);
        $customResponse = $middleware->handle($customRequest, fn () => response(config('session.domain')));
        $this->assertSame('', $customResponse->getContent());
        $this->assertSame('original.test', config('session.domain'));
    }
}
