<?php

namespace App\Filament\Resources\EvenementResource\Pages;

use App\Filament\Resources\EvenementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEvenements extends ManageRecords
{
    protected static string $resource = EvenementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajouter à l’agenda')];
    }
}
