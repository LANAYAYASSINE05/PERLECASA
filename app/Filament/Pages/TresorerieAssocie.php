<?php

namespace App\Filament\Pages;

use App\Filament\Support\FiltrePeriodeAssocie;
use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class TresorerieAssocie extends PageAssocie
{
    use FiltrePeriodeAssocie;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $title = 'Trésorerie';

    protected static ?string $slug = 'espace-associe/tresorerie';

    protected static ?int $navigationSort = 3;

    protected function periodeParDefaut(): string
    {
        return 'mois';
    }

    protected function getFooterWidgets(): array
    {
        return [Widgets\SyntheseTresorerie::class];
    }
}
