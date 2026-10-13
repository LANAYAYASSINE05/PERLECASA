<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets\GraphiqueCapital;
use App\Models\User;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Tous les associés et leur part du capital, sans leurs coordonnées. */
class CapitalAssocie extends PageAssocie implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associe-tableau';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Répartition du capital';

    protected static ?string $title = 'Répartition du capital';

    protected static ?string $slug = 'espace-associe/capital';

    protected static ?int $navigationSort = 2;

    protected function getHeaderWidgets(): array
    {
        return [GraphiqueCapital::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->associesAvecSoldes())
            ->defaultSort('apports_sum_montant', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Aucun associé')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Associé')->weight('bold')
                    ->description(fn (User $u) => $u->is(auth()->user()) ? 'Vous' : null),
                Tables\Columns\TextColumn::make('net')->label('Net investi')->alignEnd()
                    ->state(fn (User $u) => $u->netCharge())
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('part')->label('Part')->alignEnd()->badge()
                    ->color(fn (User $u) => $u->is(auth()->user()) ? 'warning' : 'gray')
                    ->state(fn (User $u) => User::part($u->netCharge()))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ').' %'),
            ]);
    }
}
