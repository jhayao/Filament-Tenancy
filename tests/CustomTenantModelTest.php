<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\Tests\Fixtures\Organization;

// Run the complete HTTP, Livewire, provisioning and retry suite with a custom model.
class CustomTenantModelTest extends WorkspaceTest
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-tenancy.tenant_model', Organization::class);
    }

    public function test_custom_model_is_used_by_panel_memberships_and_stancl(): void
    {
        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Organization', 'slug' => 'organization']);

        $this->assertInstanceOf(Organization::class, $workspace);
        $this->assertInstanceOf(Organization::class, $owner->workspaces()->first());
        $this->assertInstanceOf(Organization::class, tenancy()->find($workspace->id));
        $this->assertSame(Organization::class, Filament::getPanel('admin')->getTenantModel());
        $this->assertTrue($owner->canAccessTenant($workspace));

        $this->expectException(ValidationException::class);
        app(CreateWorkspace::class)->create($owner, ['name' => 'Duplicate', 'slug' => 'organization']);
    }
}
