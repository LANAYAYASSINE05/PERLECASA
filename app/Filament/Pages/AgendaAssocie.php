<?php

namespace App\Filament\Pages;

use App\Enums\TypeEvenement;
use App\Filament\Resources\EvenementResource;
use App\Filament\Support\PageAssocie;
use App\Models\Evenement;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AgendaAssocie extends PageAssocie implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associe-tableau';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Vie sociale';

    protected static ?string $title = 'Agenda';

    protected static ?string $slug = 'espace-associe/agenda';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $nombre = Evenement::aVenir()->where('date_debut', '<=', now()->addDays(30))->count();

        return $nombre ? (string) $nombre : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Evenement::query())
            ->defaultSort('date_debut')
            ->emptyStateHeading('Aucune échéance')
            ->columns(EvenementResource::colonnes())
            ->filters([
                Tables\Filters\TernaryFilter::make('quand')->label('Échéances')
                    ->placeholder('Toutes')->trueLabel('À venir')->falseLabel('Passées')
                    ->default(true)
                    ->queries(
                        true: fn ($q) => $q->aVenir(),
                        false: fn ($q) => $q->where('date_debut', '<', now()->startOfDay())->reorder('date_debut', 'desc'),
                        blank: fn ($q) => $q,
                    ),
                Tables\Filters\SelectFilter::make('type')->options(TypeEvenement::class),
            ]);
    }
}
