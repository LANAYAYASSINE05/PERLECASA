<?php

namespace App\Filament\Pages;

use App\Filament\Support\FiltrePeriodeAssocie;
use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class ResultatAssocie extends PageAssocie
{
    use FiltrePeriodeAssocie;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $title = 'Résultat';

    protected static ?string $slug = 'espace-associe/resultat';

    protected static ?int $navigationSort = 2;

    protected function getFooterWidgets(): array
    {
        return [Widgets\ResultatSimplifie::class, Widgets\EvolutionResultat::class, Widgets\RepartitionDepenses::class];
    }
}
