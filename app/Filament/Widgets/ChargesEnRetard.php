<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ChargeFixeResource;
use App\Filament\Widgets\Concerns\PaginationComplete;
use App\Models\ChargeFixe;
use App\Support\Montant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ChargesEnRetard extends TableWidget
{
    use PaginationComplete;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Charges fixes à régler';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('charges');
    }

    public function table(Table $table): Table
    {
        $aRegler = ChargeFixe::where('actif', true)->get()
            ->filter(fn (ChargeFixe $c) => $c->prochainePeriode() <= now()->format('Y-m'))
            ->modelKeys();

        return $table
            ->query(ChargeFixe::query()->whereKey($aRegler))
            ->defaultSort('libelle')
            ->recordClasses('pc-fiche-attente')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Toutes les charges du mois sont réglées')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                Tables\Columns\TextColumn::make('libelle')->label('Charge'),
                Tables\Columns\TextColumn::make('prochaine')->label('Période')->badge()
                    ->state(fn (ChargeFixe $c) => $c->prochainePeriode())
                    ->color(fn (ChargeFixe $c) => $c->estEnRetard() ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('montant')->alignEnd()
                    ->formatStateUsing(fn ($state) => Montant::mad($state)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tout')->label('Payer')->link()
                    ->url(ChargeFixeResource::getUrl('index')),
            ]);
    }
}
