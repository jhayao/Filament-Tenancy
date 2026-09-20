<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WorkspaceProfile extends EditTenantProfile
{
    protected ?string $logoToDelete = null;

    public static function getLabel(): string
    {
        return __('filament-tenancy::tenancy.profile.label');
    }

    public static function canView(Model $tenant): bool
    {
        $user = Filament::auth()->user();

        return $user !== null
            && $user->canAccessTenant($tenant)
            && $tenant->users()->whereKey($user->getKey())->wherePivot('is_owner', true)->exists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-tenancy::tenancy.profile.details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-tenancy::tenancy.profile.name'))
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('logo')
                            ->label(__('filament-tenancy::tenancy.profile.logo'))
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->disk(fn (): string => $this->logoDisk())
                            ->directory(fn (): string => $this->logoDirectory())
                            ->visibility('public')
                            ->deletable()
                            ->openable()
                            ->downloadable(false)
                            ->preventFilePathTampering(true, fn (string $file): bool => $file === $this->tenant?->getAttribute('logo_path') || str_starts_with($file, $this->logoDirectory().'/')),
                        Textarea::make('description')
                            ->label(__('filament-tenancy::tenancy.profile.description'))
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('filament-tenancy::tenancy.profile.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('filament-tenancy::tenancy.profile.phone'))
                            ->tel()
                            ->maxLength(50),
                    ])
                    ->columns(2),
                Section::make(__('filament-tenancy::tenancy.profile.workspace_address'))
                    ->schema([
                        TextInput::make('workspace_url')
                            ->label(__('filament-tenancy::tenancy.profile.url'))
                            ->readOnly()
                            ->dehydrated(false)
                            ->suffixAction(
                                Action::make('copyWorkspaceUrl')
                                    ->label(__('filament-tenancy::tenancy.profile.copy_url'))
                                    ->icon(Heroicon::OutlinedClipboardDocument)
                                    ->extraAttributes(fn (): array => [
                                        'x-on:click' => 'navigator.clipboard.writeText('.json_encode($this->getWorkspaceUrl()).')',
                                    ]),
                            )
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data')
            ->model($this->tenant);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['logo'] = $this->tenant->getAttribute('logo_path');
        $data['workspace_url'] = $this->getWorkspaceUrl();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = array_intersect_key($data, array_flip(['name', 'description', 'email', 'phone', 'logo']));
        $oldLogo = $this->tenant->getAttribute('logo_path');
        $newLogo = $data['logo'] ?? null;

        unset($data['logo']);

        if (is_string($newLogo) && $newLogo !== $oldLogo) {
            $data['logo_path'] = $newLogo;

            $this->logoToDelete = $oldLogo;
        } elseif (blank($newLogo) && filled($oldLogo)) {
            $data['logo_path'] = null;
            $this->logoToDelete = $oldLogo;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (filled($this->logoToDelete)) {
            Storage::disk($this->logoDisk())->delete($this->logoToDelete);
            $this->logoToDelete = null;
        }
    }

    protected function logoDisk(): string
    {
        return (string) config('filament-tenancy.profile.logo_disk', 'public');
    }

    protected function logoDirectory(): string
    {
        return trim((string) config('filament-tenancy.profile.logo_directory', 'workspaces'), '/').'/'.$this->tenant->getKey().'/profile';
    }

    protected function getWorkspaceUrl(): string
    {
        return Filament::getUrl($this->tenant);
    }
}
