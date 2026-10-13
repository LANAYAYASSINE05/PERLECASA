<?php

namespace App\Filament\Pages;

use App\Filament\Support\FiltrePeriodeAssocie;
use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class SocieteAssocie extends PageAssocie
{
    use FiltrePeriodeAssocie;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $title = 'La société';

    protected static ?string $slug = 'espace-associe/societe';

    protected static ?int $navigationSort = 2;

    protected function periodeParDefaut(): string
    {
        return 'annee';
    }

    protected function getFooterWidgets(): array
    {
        return [
            Widgets\ResultatSimplifie::class,
            Widgets\SyntheseTresorerie::class,
            Widgets\EvolutionResultat::class,
            Widgets\RepartitionDepenses::class,
            Widgets\SyntheseProgrammes::class,
            Widgets\ResteAEncaisser::class,
        ];
    }
}
