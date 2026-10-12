<?php

namespace App\Filament\Pages;

use App\Enums\CategorieCharge;
use App\Filament\Support\PageAssocie;
use App\Filament\Widgets\ResumeCharges;
use App\Models\ChargeFixe;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Charges fixes en lecture seule : budget, dernier règlement, retards. */
class ChargesAssocie extends PageAssocie implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associe-tableau';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'La société';

    protected static ?string $title = 'Charges fixes';

    protected static ?string $slug = 'espace-associe/charges';

    protected static ?int $navigationSort = 3;

    protected function getHeaderWidgets(): array
    {
        return [ResumeCharges::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ChargeFixe::query()->where('actif', true)->with('fournisseur')->withMax('decaissements', 'periode'))
            ->defaultSort('libelle')
            ->paginated(false)
            ->emptyStateHeading('Aucune charge fixe active')
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->label('Charge')->searchable()->weight('bold')
                    ->description(fn (ChargeFixe $c) => $c->fournisseur?->raison_sociale),
                Tables\Columns\TextColumn::make('categorie')->label('Catégorie')->badge()->color('gray')->visibleFrom('md'),
                Tables\Columns\TextColumn::make('periodicite')->label('Périodicité')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('mensuel')->label('Par mois')->alignEnd()->visibleFrom('md')
                    ->state(fn (ChargeFixe $c) => ResumeCharges::mensuel($c))
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('decaissements_max_periode')->label('Dernière période réglée')->placeholder('Jamais')->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('etat')->label('État')->badge()
                    ->state(fn (ChargeFixe $c) => $c->estEnRetard() ? 'En retard' : 'À jour')
                    ->color(fn (string $state) => $state === 'En retard' ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')->label('Catégorie')->options(CategorieCharge::class),
            ]);
    }
}
