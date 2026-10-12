<?php

namespace App\Filament\Resources\DocumentAssocieResource\Pages;

use App\Filament\Resources\DocumentAssocieResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentsAssocies extends ManageRecords
{
    protected static string $resource = DocumentAssocieResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Déposer un document')];
    }
}
