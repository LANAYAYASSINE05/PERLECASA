<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class ProgrammesAssocie extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $title = 'Programmes';

    protected static ?string $slug = 'espace-associe/programmes';

    protected static ?int $navigationSort = 4;

    protected function getHeaderWidgets(): array
    {
        return [Widgets\SyntheseProgrammes::class];
    }
}
