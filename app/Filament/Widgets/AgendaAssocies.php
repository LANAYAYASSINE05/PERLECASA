<?php

namespace App\Filament\Widgets;

use App\Enums\TypeEvenement;
use App\Filament\Resources\EvenementResource;
use App\Models\Evenement;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AgendaAssocies extends TableWidget
{
    protected static ?string $heading = 'Agenda';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Evenement::query())
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
