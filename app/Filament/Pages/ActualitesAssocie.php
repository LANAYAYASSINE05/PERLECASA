<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Models\Actualite;
use Illuminate\Contracts\Pagination\Paginator;
use Livewire\WithPagination;

class ActualitesAssocie extends PageAssocie
{
    use WithPagination;

    protected static string $view = 'filament.pages.actualites-associe';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Vie sociale';

    protected static ?string $title = 'Actualités';

    protected static ?string $slug = 'espace-associe/actualites';

    protected static ?int $navigationSort = 1;

    public function getActualites(): Paginator
    {
        return Actualite::query()->with('auteur')
            ->orderByDesc('epingle')->latest()
            ->simplePaginate(10);
    }
}
