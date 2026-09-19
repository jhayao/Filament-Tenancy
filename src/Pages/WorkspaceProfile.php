<?php

namespace Liern\FilamentTenancy\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;

class WorkspaceProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Workspace Profile';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
