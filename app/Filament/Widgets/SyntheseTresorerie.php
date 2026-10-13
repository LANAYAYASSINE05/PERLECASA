<?php

namespace App\Filament\Widgets;

use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Trésorerie de la société en lecture seule, pour l'associé : soldes du jour et flux du mois. */
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
        $comptes = Compte::where('actif', true)->orderBy('type')->orderBy('nom')->get();
        $mois = fn ($query) => (float) $query->whereBetween('date_operation', [now()->startOfMonth(), now()->endOfMonth()])->sum('montant');

        return [
            Stat::make('Trésorerie disponible', Montant::mad($comptes->sum(fn (Compte $c) => $c->solde())))
                ->description('Banques et caisses, à ce jour')
                ->descriptionIcon('heroicon-m-building-library')->color('primary'),
            Stat::make('Encaissé ce mois', Montant::mad($mois(Encaissement::query())))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->color('success'),
            Stat::make('Décaissé ce mois', Montant::mad($mois(Decaissement::query())))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->color('danger'),
            ...$comptes->map(fn (Compte $c) => Stat::make($c->nom, Montant::mad($c->solde()))
                ->description($c->type->getLabel())->color('gray'))->all(),
        ];
    }
}
