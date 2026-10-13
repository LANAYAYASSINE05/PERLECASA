<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;
use App\Models\Evenement;

class VieSocialeAssocie extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $title = 'Documents & agenda';

    protected static ?string $slug = 'espace-associe/vie-sociale';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $nombre = Evenement::aVenir()->where('date_debut', '<=', now()->addDays(30))->count();

        return $nombre ? (string) $nombre : null;
    }

    protected function getHeaderWidgets(): array
    {
        return [Widgets\DernieresActualites::class, Widgets\AgendaAssocies::class, Widgets\DocumentsPartages::class];
    }
}
