<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Tous les associés et leur part : noms visibles, sans coordonnées ni RIB. */
class TableauCapital extends TableWidget
{
    protected static ?string $heading = 'Associés et parts du capital';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public function table(Table $table): Table
    {
        $montant = fn ($state) => Montant::mad((float) $state);

        return $table
            ->query(fn () => User::query()->associesAvecSoldes())
            ->defaultSort('apports_sum_montant', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Aucun associé')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Associé')->weight('bold')
                    ->icon(fn (User $u) => $u->is(auth()->user()) ? 'heroicon-s-user-circle' : null)->iconColor('warning')
                    ->description(fn (User $u) => $u->is(auth()->user()) ? 'Vous' : null),
                Tables\Columns\TextColumn::make('apports_sum_montant')->label('Apporté')->alignEnd()->color('success')->visibleFrom('md')
                    ->formatStateUsing($montant)->default(0),
                Tables\Columns\TextColumn::make('remboursements_sum_montant')->label('Remboursé')->alignEnd()->color('danger')->visibleFrom('md')
                    ->formatStateUsing($montant)->default(0),
                Tables\Columns\TextColumn::make('net')->label('Net investi')->alignEnd()->weight('bold')
                    ->state(fn (User $u) => $u->netCharge())->formatStateUsing($montant)
                    ->summarize(Summarizer::make()->label('Total')
                        ->using(fn () => User::query()->associesAvecSoldes()->get()->sum(fn (User $u) => $u->netCharge()))
                        ->formatStateUsing($montant)),
                Tables\Columns\TextColumn::make('part')->label('Part')->alignEnd()->badge()
                    ->color(fn (User $u) => $u->is(auth()->user()) ? 'warning' : 'gray')
                    ->state(fn (User $u) => User::part($u->netCharge()))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ').' %'),
            ]);
    }
}
