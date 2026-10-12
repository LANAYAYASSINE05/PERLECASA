<?php

namespace App\Filament\Widgets;

use App\Enums\StatutAppartement;
use App\Filament\Pages\FicheProgramme;
use App\Models\Appartement;
use App\Models\Programme;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** Lots d'un programme ou de tous, avec prix catalogue et prix de vente, jamais le client. */
class LotsProgramme extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Lots';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    private function programmeId(): ?int
    {
        return FicheProgramme::programmeChoisi($this->filters);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Appartement::query()
                ->with(['programme', 'vente'])
                ->when($this->programmeId(), fn ($q, int $id) => $q->where('programme_id', $id)))
            ->defaultSort('reference')
            ->emptyStateHeading('Aucun lot')
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label('Lot')->searchable()->sortable()->weight('bold')
                    ->description(fn (Appartement $a) => $a->typologie),
                Tables\Columns\TextColumn::make('programme.nom')->label('Programme')
                    ->hidden(fn () => (bool) $this->programmeId()),
                Tables\Columns\TextColumn::make('surface')->alignEnd()->sortable()->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ').' m²'),
                Tables\Columns\TextColumn::make('prix')->label('Prix catalogue')->alignEnd()->sortable()
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('prix_m2')->label('Prix / m²')->alignEnd()->visibleFrom('lg')
                    ->state(fn (Appartement $a) => (float) $a->surface > 0 ? (float) $a->prix / (float) $a->surface : null)
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state))->placeholder('—'),
                Tables\Columns\TextColumn::make('vente.prix_vente')->label('Vendu à')->alignEnd()->placeholder('—')->visibleFrom('md')
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('statut')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')->options(StatutAppartement::class),
                Tables\Filters\SelectFilter::make('programme_id')->label('Programme')
                    ->options(fn () => Programme::orderBy('nom')->pluck('nom', 'id'))
                    ->visible(fn () => ! $this->programmeId()),
                Tables\Filters\SelectFilter::make('typologie')
                    ->options(fn () => Appartement::query()->distinct()->orderBy('typologie')->pluck('typologie', 'typologie')),
            ]);
    }
}
