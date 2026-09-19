<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Liern\FilamentTenancy\Services\CreateWorkspace;

class DisabledRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-tenancy.registration_page', null);
    }

    public function test_registration_is_unavailable_but_existing_workspaces_work(): void
    {
        $owner = $this->user();
        app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->actingAs($owner);
        $this->assertFalse(Filament::getPanel('admin')->hasTenantRegistration());
        $this->assertNull(Filament::getPanel('admin')->getTenantRegistrationUrl());
        $this->get('/admin/new')->assertNotFound();
        $this->get('/admin/workspaces/acme')->assertOk()->assertDontSee('Create workspace');
    }
}
