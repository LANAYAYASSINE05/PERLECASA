<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DecaissementResource;
use App\Filament\Widgets\Concerns\PaginationComplete;
use App\Models\Decaissement;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CaisseNonJustifiee extends TableWidget
{
    use PaginationComplete;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Opérations de caisse non justifiées';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('decaissements');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Decaissement::query()->caisseNonJustifiee()->with('compte'))
            ->defaultSort('date_operation')
            ->recordClasses('pc-fiche-alerte')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Toutes les opérations de caisse sont justifiées')
            ->emptyStateIcon('heroicon-o-document-check')
            ->columns([
                Tables\Columns\TextColumn::make('date_operation')->label('Date')->date('d/m/Y')
                    ->description(fn (Decaissement $d) => $d->date_operation->diffForHumans()),
                Tables\Columns\TextColumn::make('operateur')->label('Opérateur')
                    ->description(fn (Decaissement $d) => $d->motif)->wrap(),
                Tables\Columns\TextColumn::make('montant')->alignEnd()->weight('bold')->color('danger')
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tout')->label('Tout voir')->link()
                    ->url(DecaissementResource::getUrl('index', ['activeTab' => 'non_justifiees'])),
            ]);
    }
}
