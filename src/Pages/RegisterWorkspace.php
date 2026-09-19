<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Services\CreateWorkspace;

class RegisterWorkspace extends RegisterTenant
{
    protected string $view = 'filament-tenancy::pages.register-workspace';

    protected bool $hasTopbar = false;

    protected array $extraBodyAttributes = ['class' => 'lw-standalone-page'];

    public static function getLabel(): string
    {
        return __('filament-tenancy::tenancy.create');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('filament-tenancy::tenancy.name'))->required()->maxLength(255),
            TextInput::make('slug')->label(__('filament-tenancy::tenancy.slug'))->helperText(__('filament-tenancy::tenancy.slug_help'))->required()->maxLength(63)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return app(CreateWorkspace::class)->create(Filament::auth()->user(), $data);
    }

    protected function getRedirectUrl(): ?string
    {
        return Provisioning::getUrl(tenant: $this->tenant);
    }
}
