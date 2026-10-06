<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Dashboard;

class TableauDeBord extends Dashboard
{
    protected static ?string $title = 'Tableau de bord';

    protected static ?string $navigationLabel = 'Tableau de bord';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->accede('tableau');
    }

    public function getWidgets(): array
    {
        return [
            Widgets\BandeauAccueil::class,
            Widgets\ChiffresFinance::class,
            Widgets\FluxMensuels::class,
            Widgets\ChargesEnRetard::class,
            Widgets\CaisseNonJustifiee::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return ['md' => 2];
    }
}
