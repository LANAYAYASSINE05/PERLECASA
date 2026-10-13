<?php

namespace App\Filament\Widgets;

use App\Models\Actualite;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\WithPagination;

/** Annonces de l'administrateur, épinglées d'abord. */
class DernieresActualites extends Widget
{
    use WithPagination;

    protected static string $view = 'filament.widgets.dernieres-actualites';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function getActualites(): Paginator
    {
        return Actualite::query()->with('auteur')
            ->orderByDesc('epingle')->latest()
            ->simplePaginate(5, pageName: 'actualites');
    }
}
