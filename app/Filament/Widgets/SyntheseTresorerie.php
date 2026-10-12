<?php

namespace App\Filament\Widgets;

use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Trésorerie de la société en lecture seule, pour l'associé. */
class SyntheseTresorerie extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Trésorerie de la société';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    protected function getStats(): array
    {
        $debut = now()->startOfMonth();
        $comptes = Compte::where('actif', true)->orderBy('type')->orderBy('nom')->get();

        return [
            Stat::make('Trésorerie disponible', Montant::mad($comptes->sum(fn (Compte $c) => $c->solde())))
                ->description('Banques et caisses')
                ->descriptionIcon('heroicon-m-building-library')->color('primary'),
            Stat::make('Encaissé ce mois', Montant::mad((float) Encaissement::where('date_operation', '>=', $debut)->sum('montant')))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->description('Toutes origines')->color('success'),
            Stat::make('Décaissé ce mois', Montant::mad((float) Decaissement::where('date_operation', '>=', $debut)->sum('montant')))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->description('Toutes natures')->color('danger'),
            ...$comptes->map(fn (Compte $c) => Stat::make($c->nom, Montant::mad($c->solde()))
                ->description($c->type->getLabel())->color('gray'))->all(),
        ];
    }
}
