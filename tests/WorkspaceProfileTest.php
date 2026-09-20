<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Pages\WorkspaceProfile;
use Liern\FilamentTenancy\Services\CreateWorkspace;
use Liern\FilamentTenancy\TenancyPlugin;
use Livewire\Livewire;

class WorkspaceProfileTest extends TestCase
{
    public function test_only_workspace_owners_can_open_the_profile_page(): void
    {
        $owner = $this->user();
        $member = $this->user('member@example.test');
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $workspace->users()->attach($member->getKey(), ['is_owner' => false]);

        $url = $this->profileUrl($owner, $workspace);

        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Workspace profile');
        $this->actingAs($member)->get($url)->assertNotFound();

        $this->actingAs($owner);
        $this->assertTrue(WorkspaceProfile::canView($workspace));
        $this->actingAs($this->user('stranger@example.test'));
        $this->assertFalse(WorkspaceProfile::canView($workspace));
    }

    public function test_owner_can_update_profile_fields_without_changing_workspace_identity(): void
    {
        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $this->setWorkspace($owner, $workspace);

        Livewire::test(WorkspaceProfile::class)
            ->fillForm([
                'name' => 'Acme Updated',
                'description' => 'A better workspace description.',
                'email' => 'hello@acme.test',
                'phone' => '+65 5555 0100',
                'workspace_url' => Filament::getUrl($workspace),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $workspace = $workspace->fresh();
        $workspaceUrl = Filament::getUrl($workspace);
        $this->assertSame('Acme Updated', $workspace->name);
        $this->assertSame('acme', $workspace->slug);
        $this->assertSame(ProvisioningStatus::Ready, $workspace->status);
        $this->assertSame('A better workspace description.', $workspace->getAttribute('description'));
        $this->assertSame('hello@acme.test', $workspace->getAttribute('email'));
        $this->assertSame('+65 5555 0100', $workspace->getAttribute('phone'));
        $this->assertSame('/admin/workspaces/acme', parse_url($workspaceUrl, PHP_URL_PATH));
    }

    public function test_replacing_or_removing_a_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $owner = $this->user();
        $workspace = app(CreateWorkspace::class)->create($owner, ['name' => 'Acme', 'slug' => 'acme']);
        $oldPath = 'workspaces/'.$workspace->getKey().'/profile/old.png';
        $newPath = 'workspaces/'.$workspace->getKey().'/profile/new.png';
        Storage::disk('public')->put($oldPath, 'old');
        Storage::disk('public')->put($newPath, 'new');
        $workspace->update(['logo_path' => $oldPath]);
        $this->setWorkspace($owner, $workspace);

        Livewire::test(WorkspaceProfile::class)
            ->fillForm(['logo' => [$newPath]])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertSame($newPath, $workspace->fresh()->getAttribute('logo_path'));

        Livewire::test(WorkspaceProfile::class)
            ->fillForm(['logo' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing($newPath);
        $this->assertNull($workspace->fresh()->getAttribute('logo_path'));
    }

    public function test_profile_can_be_disabled_without_removing_the_plugin(): void
    {
        config(['filament-tenancy.profile.enabled' => false]);

        $panel = Panel::make()->id('profile-disabled')->plugin(TenancyPlugin::make());

        $this->assertFalse($panel->hasTenantProfile());
        $this->assertTrue($panel->hasTenancy());
    }

    private function profileUrl(object $user, object $workspace): string
    {
        $this->setWorkspace($user, $workspace);

        return Filament::getTenantProfileUrl();
    }

    private function setWorkspace(object $user, object $workspace): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        Filament::setTenant($workspace, isQuiet: true);
    }
}
