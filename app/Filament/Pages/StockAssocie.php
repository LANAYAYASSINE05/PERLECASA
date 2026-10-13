<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;

class StockAssocie extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $title = 'Stock des appartements';

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $slug = 'espace-associe/stock';

    protected static ?int $navigationSort = 6;

    protected function getHeaderWidgets(): array
    {
        return [Widgets\ResumeStock::class, Widgets\LotsProgramme::class];
    }
}
