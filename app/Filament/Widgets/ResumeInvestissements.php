<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MesInvestissements;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResumeInvestissements extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    protected function getStats(): array
    {
        $associe = auth()->user();
        $lien = MesInvestissements::getUrl();

        return [
            Stat::make('Total apporté', Montant::mad($associe->totalApporte()))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->description('Vos apports à la société')
                ->color('success')->url($lien),
            Stat::make('Total remboursé', Montant::mad($associe->totalRembourse()))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->description('Remboursements et distributions reçus')
                ->color('danger')->url($lien),
            Stat::make('Net investi', Montant::mad($associe->netInvesti()))
                ->descriptionIcon('heroicon-m-banknotes')->description('Apporté moins remboursé')
                ->color('primary')->url($lien),
            Stat::make('Part du capital', number_format($associe->partDuCapital(), 2, ',', ' ').' %')
                ->descriptionIcon('heroicon-m-chart-pie')->description('Sur le net investi de tous les associés')
                ->color('warning')->url($lien),
        ];
    }
}
