<?php

namespace App\Filament\Resources\ActualiteResource\Pages;

use App\Filament\Resources\ActualiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageActualites extends ManageRecords
{
    protected static string $resource = ActualiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Publier une actualité')];
    }
}
