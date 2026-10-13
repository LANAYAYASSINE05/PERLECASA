<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class ResultatAssocie extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $title = 'Résultat';

    protected static ?string $slug = 'espace-associe/resultat';

    protected static ?int $navigationSort = 3;

    protected function getHeaderWidgets(): array
    {
        return [Widgets\ResultatSimplifie::class, Widgets\EvolutionResultat::class];
    }
}
