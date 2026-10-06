<?php

namespace App\Filament\Resources\CompteResource\Pages;

use App\Filament\Resources\CompteResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageComptes extends ManageRecords
{
    protected static string $resource = CompteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
