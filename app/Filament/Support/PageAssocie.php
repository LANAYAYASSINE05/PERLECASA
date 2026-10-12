<?php

namespace App\Filament\Support;

use Filament\Pages\Page;

/** Page de synthèse en lecture seule de l'espace associé : uniquement des widgets, empilés. */
abstract class PageAssocie extends Page
{
    protected static string $view = 'filament.pages.associe';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
