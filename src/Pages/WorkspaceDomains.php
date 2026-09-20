<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Support\WorkspaceDomainManager;

class WorkspaceDomains extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $slug = 'domains';

    protected static string $view = 'filament-tenancy::pages.workspace-domains';

    public ?array $data = [];

    public ?Model $tenant = null;

    public static function getNavigationLabel(): string
    {
        return __('filament-tenancy::tenancy.domains.label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();
        $user = Filament::auth()->user();

        return config('filament-tenancy.custom_domains.enabled')
            && $tenant !== null
            && $user !== null
            && $user->canAccessTenant($tenant)
            && $tenant->users()->whereKey($user->getKey())->wherePivot('is_owner', true)->exists();
    }

    public function mount(): void
    {
        $this->tenant = Filament::getTenant();

        abort_unless(static::canAccess(), 404);

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('domain')
                    ->label(__('filament-tenancy::tenancy.domains.domain'))
                    ->placeholder(__('filament-tenancy::tenancy.domains.domain_placeholder'))
                    ->helperText(__('filament-tenancy::tenancy.domains.domain_help'))
                    ->required()
                    ->maxLength(253)
                    ->autocomplete('off'),
            ])
            ->statePath('data');
    }

    public function getDomains(): \Illuminate\Database\Eloquent\Collection
    {
        return WorkspaceDomain::query()
            ->where('workspace_id', $this->tenant?->getKey())
            ->latest()
            ->get();
    }

    public function addDomain(): void
    {
        abort_unless(static::canAccess(), 404);

        $data = $this->form->getState();
        app(WorkspaceDomainManager::class)->add($this->tenant->getKey(), (string) ($data['domain'] ?? ''));

        $this->data = [];
        $this->form->fill();

        Notification::make()
            ->success()
            ->title(__('filament-tenancy::tenancy.domains.notifications.added'))
            ->send();
    }

    public function verifyDomain(int $domainId): void
    {
        abort_unless(static::canAccess(), 404);

        $domain = $this->getOwnedDomain($domainId);
        $verified = app(WorkspaceDomainManager::class)->verify(
            $domain,
            'lona-domain-verify:'.Filament::auth()->id().':'.$domain->getKey(),
        );

        $notification = Notification::make()->title($verified
            ? __('filament-tenancy::tenancy.domains.notifications.verified')
            : __('filament-tenancy::tenancy.domains.notifications.pending'));

        if ($verified) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    public function removeDomain(int $domainId): void
    {
        abort_unless(static::canAccess(), 404);

        app(WorkspaceDomainManager::class)->remove($this->getOwnedDomain($domainId));

        Notification::make()
            ->success()
            ->title(__('filament-tenancy::tenancy.domains.notifications.removed'))
            ->send();
    }

    protected function getOwnedDomain(int $domainId): WorkspaceDomain
    {
        return WorkspaceDomain::query()
            ->whereKey($domainId)
            ->where('workspace_id', $this->tenant->getKey())
            ->firstOrFail();
    }
}
