<?php

namespace App\Filament\Resources\FournisseurResource\Pages;

use App\Filament\Resources\FournisseurResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFournisseurs extends ManageRecords
{
    protected static string $resource = FournisseurResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
