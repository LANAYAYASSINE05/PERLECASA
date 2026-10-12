<?php

namespace App\Filament\Widgets;

use App\Filament\Support\ImpressionPiece;
use App\Models\Encaissement;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class MesApports extends TableWidget
{
    protected static ?string $heading = 'Mes apports';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => auth()->user()->apports()->with('compte')->getQuery())
            ->defaultSort('date_operation', 'desc')
            ->emptyStateHeading('Aucun apport enregistré')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->weight('bold'),
                Tables\Columns\TextColumn::make('date_operation')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('libelle')->label('Libellé')->placeholder('—')->visibleFrom('md'),
                Tables\Columns\TextColumn::make('compte.nom')->label('Compte crédité')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('mode')->badge()->color('gray')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()->weight('bold')->color('success')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
            ])
            ->actions([
                ImpressionPiece::make('Reçu', fn (Encaissement $e) => route('encaissements.recu', $e)),
            ]);
    }
}
