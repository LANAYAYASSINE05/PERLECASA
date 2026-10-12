<?php

namespace App\Filament\Widgets;

use App\Models\Vente;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/** Ventes pas encore soldées, identifiées par l'appartement : jamais par le client. */
class ResteAEncaisser extends TableWidget
{
    protected static ?string $heading = 'Reste à encaisser par vente';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        $encaisse = '(select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)';

        return $table
            ->query(fn () => Vente::query()
                ->with('appartement.programme')
                ->select('ventes.*')
                ->selectRaw("{$encaisse} as encaisse")
                ->selectRaw("ventes.prix_vente - {$encaisse} as reste")
                ->where(DB::raw("ventes.prix_vente - {$encaisse}"), '>', 0.001))
            ->defaultSort('reste', 'desc')
            ->emptyStateHeading('Toutes les ventes sont soldées')
            ->columns([
                Tables\Columns\TextColumn::make('appartement.reference')->label('Appartement')->weight('bold')
                    ->description(fn (Vente $v) => $v->appartement?->typologie),
                Tables\Columns\TextColumn::make('appartement.programme.nom')->label('Programme')->visibleFrom('md'),
                Tables\Columns\TextColumn::make('date_vente')->label('Vendu le')->date('d/m/Y')->sortable()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('prix_vente')->label('Prix')->alignEnd()->visibleFrom('lg')
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
                Tables\Columns\TextColumn::make('encaisse')->label('Encaissé')->alignEnd()->color('success')->visibleFrom('lg')
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
                Tables\Columns\TextColumn::make('reste')->label('Reste')->alignEnd()->sortable()->weight('bold')->color('warning')
                    ->formatStateUsing(fn ($state) => Montant::mad($state))
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Montant::mad($state))),
            ]);
    }
}
