<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Montant;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Récapitulatif des apports et remboursements de chaque associé, pour l'administrateur. */
class Associes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.associes';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $title = 'Associés';

    protected static ?string $slug = 'associes';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->accede('associes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->associesAvecSoldes())
            ->defaultSort('name')
            ->emptyStateHeading('Aucun associé')
            ->emptyStateDescription('Créez un utilisateur avec le rôle « Associé » dans Utilisateurs.')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Associé')->searchable()->sortable()->weight('bold')
                    ->description(fn (User $u) => $u->email),
                Tables\Columns\IconColumn::make('actif')->boolean()->alignCenter()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('apports_sum_montant')->label('Apporté')->alignEnd()->sortable()->color('success')
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state))->default(0),
                Tables\Columns\TextColumn::make('remboursements_sum_montant')->label('Remboursé')->alignEnd()->sortable()->color('danger')
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state))->default(0),
                Tables\Columns\TextColumn::make('net')->label('Net investi')->alignEnd()->weight('bold')
                    ->state(fn (User $u) => $u->netCharge())
                    ->formatStateUsing(fn ($state) => Montant::mad((float) $state)),
                Tables\Columns\TextColumn::make('part')->label('Part')->alignEnd()->badge()->color('warning')
                    ->state(fn (User $u) => User::part($u->netCharge()))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ').' %'),
            ])
            ->actions([
                Tables\Actions\Action::make('releve')->label('Relevé')
                    ->icon('heroicon-o-table-cells')->color('success')
                    ->url(fn (User $u) => route('associes.releve', $u))
                    ->extraAttributes(['download' => true]),
            ]);
    }
}
