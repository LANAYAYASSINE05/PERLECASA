<?php

namespace App\Filament\Resources\BonCommandeResource\Pages;

use App\Filament\Resources\BonCommandeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBonsCommande extends ManageRecords
{
    protected static string $resource = BonCommandeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nouveau bon de commande')->modalWidth('5xl')];
    }
}
