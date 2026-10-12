<?php

namespace App\Filament\Widgets;

use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class MesRemboursements extends TableWidget
{
    protected static ?string $heading = 'Mes remboursements';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => auth()->user()->remboursements()->with('compte')->getQuery())
            ->defaultSort('date_operation', 'desc')
            ->emptyStateHeading('Aucun remboursement enregistré')
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('N°')->weight('bold'),
                Tables\Columns\TextColumn::make('date_operation')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('motif')->label('Motif')->placeholder('—')->visibleFrom('md'),
                Tables\Columns\TextColumn::make('compte.nom')->label('Compte débité')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('mode')->badge()->color('gray')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()->weight('bold')->color('danger')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
            ]);
    }
}
