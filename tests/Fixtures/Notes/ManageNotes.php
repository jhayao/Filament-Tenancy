<?php

namespace Liern\FilamentTenancy\Tests\Fixtures\Notes;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNotes extends ManageRecords
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
