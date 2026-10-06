<?php

namespace App\Filament\Resources\EncaissementResource\Pages;

use App\Enums\TypeEncaissement;
use App\Filament\Resources\EncaissementResource;
use App\Filament\Support\Enregistrement;
use App\Models\Encaissement;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;

class ManageEncaissements extends ManageRecords
{
    protected static string $resource = EncaissementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvel encaissement')
                ->using(fn (array $data) => Enregistrement::proteger(fn () => Encaissement::create($data))),
        ];
    }

    public function getTabs(): array
    {
        $onglets = ['tous' => Tab::make('Tous')];

        foreach (TypeEncaissement::cases() as $type) {
            $onglets[$type->value] = Tab::make($type->getLabel())
                ->icon($type->getIcon())
                ->modifyQueryUsing(fn ($query) => $query->where('type', $type));
        }

        return $onglets;
    }
}
