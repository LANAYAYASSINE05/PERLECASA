<?php

namespace App\Filament\Resources\DecaissementResource\Pages;

use App\Enums\TypeDecaissement;
use App\Filament\Resources\DecaissementResource;
use App\Filament\Support\Enregistrement;
use App\Models\Decaissement;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;

class ManageDecaissements extends ManageRecords
{
    protected static string $resource = DecaissementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(DecaissementResource::caissier() ? 'Nouvelle opération de caisse' : 'Nouveau décaissement')
                ->mutateFormDataUsing(fn (array $data) => DecaissementResource::caissier()
                    ? [...$data, 'type' => TypeDecaissement::OperationCaisse->value]
                    : $data)
                ->using(fn (array $data) => Enregistrement::proteger(fn () => Decaissement::create($data))),
        ];
    }

    public function getTabs(): array
    {
        $onglets = ['tous' => Tab::make('Tous')];

        if (! DecaissementResource::caissier()) {
            foreach (TypeDecaissement::cases() as $type) {
                $onglets[$type->value] = Tab::make($type->getLabel())
                    ->icon($type->getIcon())
                    ->modifyQueryUsing(fn ($query) => $query->where('type', $type));
            }
        }

        $nonJustifiees = DecaissementResource::getEloquentQuery()->caisseNonJustifiee()->count();

        $onglets['non_justifiees'] = Tab::make('Non justifiées')
            ->icon('heroicon-o-exclamation-triangle')
            ->badge($nonJustifiees ?: null)->badgeColor('danger')
            ->modifyQueryUsing(fn ($query) => $query->caisseNonJustifiee());

        return $onglets;
    }
}
