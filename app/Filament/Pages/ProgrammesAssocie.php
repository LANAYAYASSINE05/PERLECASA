<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class ProgrammesAssocie extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $title = 'Programmes';

    protected static ?string $slug = 'espace-associe/programmes';

    protected static ?string $navigationGroup = 'Programmes immobiliers';

    protected static ?int $navigationSort = 1;

    protected function getHeaderWidgets(): array
    {
        return [Widgets\SyntheseProgrammes::class, Widgets\ResteAEncaisser::class];
    }
}
