<?php

namespace App\Filament\Widgets;

use App\Enums\StatutAppartement;
use App\Models\Appartement;
use App\Models\Programme;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lots de tous les programmes avec leur prix catalogue, jamais le client. */
class LotsProgramme extends TableWidget
{
    protected static ?string $heading = 'Lots';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Appartement::query()->with('programme'))
            ->defaultSort('reference')
            ->emptyStateHeading('Aucun lot')
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Lot')->searchable()->sortable()->weight('bold')
                    ->description(fn (Appartement $a) => $a->typologie),
                Tables\Columns\TextColumn::make('programme.nom')->label('Programme'),
                Tables\Columns\TextColumn::make('surface')->alignEnd()->sortable()->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ').' m²'),
                Tables\Columns\TextColumn::make('prix')->label('Prix')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('statut')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(StatutAppartement::class),
                Tables\Filters\SelectFilter::make('programme_id')->label('Programme')
                    ->options(fn () => Programme::orderBy('nom')->pluck('nom', 'id')),
            ]);
    }
}
