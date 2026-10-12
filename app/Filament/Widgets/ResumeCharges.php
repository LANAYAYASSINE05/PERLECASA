<?php

namespace App\Filament\Widgets;

use App\Enums\TypeDecaissement;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Budget mensuel des charges fixes actives face à ce qui a été réglé ce mois-ci. */
class ResumeCharges extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    public static function mensuel(ChargeFixe $c): float
    {
        return round((float) $c->montant / $c->periodicite->enMois(), 2);
    }

    /** @return array{budget: float, regle: float, retards: int} */
    public static function chiffres(): array
    {
        $charges = ChargeFixe::where('actif', true)->get();

        return [
            'budget' => round($charges->sum(fn (ChargeFixe $c) => self::mensuel($c)), 2),
            'regle' => (float) Decaissement::where('type', TypeDecaissement::ChargeFixe)
                ->whereBetween('date_operation', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('montant'),
            'retards' => $charges->filter(fn (ChargeFixe $c) => $c->estEnRetard())->count(),
        ];
    }

    protected function getStats(): array
    {
        $c = self::chiffres();

        return [
            Stat::make('Budget mensuel', Montant::mad($c['budget']))->description('charges actives ramenées au mois')
                ->descriptionIcon('heroicon-m-calculator')->color('primary'),
            Stat::make('Réglé ce mois', Montant::mad($c['regle']))->description(ucfirst(now()->translatedFormat('F Y')))
                ->descriptionIcon('heroicon-m-check-circle')->color('success'),
            Stat::make('Charges en retard', $c['retards'])->description($c['retards'] ? 'à régulariser' : 'tout est à jour')
                ->descriptionIcon($c['retards'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color($c['retards'] ? 'danger' : 'success'),
        ];
    }
}
