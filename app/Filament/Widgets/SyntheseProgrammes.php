<?php

namespace App\Filament\Widgets;

use App\Enums\StatutAppartement;
use App\Models\Programme;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/** Avancement commercial de chaque programme, sans détail des clients. */
class SyntheseProgrammes extends TableWidget
{
    protected static ?string $heading = 'Avancement des programmes';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        $ventes = fn (string $somme) => DB::table('ventes')
            ->join('appartements', 'appartements.id', '=', 'ventes.appartement_id')
            ->whereColumn('appartements.programme_id', 'programmes.id')
            ->selectRaw("coalesce(sum({$somme}), 0)");

        return $table
            ->query(fn () => Programme::query()
                ->withCount('appartements')
                ->withCount(['appartements as vendus_count' => fn ($q) => $q->where('statut', StatutAppartement::Vendu)])
                ->addSelect([
                    'vendu' => $ventes('ventes.prix_vente'),
                    'encaisse' => $ventes('(select coalesce(sum(montant), 0) from encaissements where encaissements.vente_id = ventes.id)'),
                ]))
            ->defaultSort('nom')
            ->paginated(false)->emptyStateHeading('Aucun programme')
            ->columns([
                Tables\Columns\TextColumn::make('nom')->label('Programme')->weight('bold')
                    ->description(fn (Programme $p) => $p->ville),
                Tables\Columns\TextColumn::make('appartements_count')->label('Lots')->alignCenter(),
                Tables\Columns\TextColumn::make('vendus_count')->label('Vendus')->alignCenter()->color('success'),
                Tables\Columns\TextColumn::make('disponibles')->label('Disponibles')->alignCenter()
                    ->state(fn (Programme $p) => $p->appartements_count - $p->vendus_count),
                Tables\Columns\TextColumn::make('vendu')->label('Chiffre d’affaires vendu')->alignEnd()->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('encaisse')->label('Encaissé')->alignEnd()->color('success')
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('reste')->label('Reste à encaisser')->alignEnd()->color('warning')->visibleFrom('md')
                    ->state(fn (Programme $p) => (float) $p->vendu - (float) $p->encaisse)
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
            ]);
    }
}
