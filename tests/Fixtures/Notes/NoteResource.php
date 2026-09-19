<?php

namespace Liern\FilamentTenancy\Tests\Fixtures\Notes;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liern\FilamentTenancy\Resources\TenantResource;
use Liern\FilamentTenancy\Tests\Fixtures\Note;

class NoteResource extends TenantResource
{
    protected static ?string $model = Note::class;

    protected static ?string $recordTitleAttribute = 'body';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make()->schema([TextInput::make('body')->required()])]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('body')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotes::route('/')];
    }
}
