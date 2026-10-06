<?php

namespace App\Filament\Resources\VenteResource\Pages;

use App\Filament\Resources\VenteResource;
use App\Filament\Support\Enregistrement;
use App\Models\Vente;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVentes extends ManageRecords
{
    protected static string $resource = VenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvelle vente')
                ->using(fn (array $data) => Enregistrement::proteger(fn () => Vente::create($data))),
        ];
    }
}
