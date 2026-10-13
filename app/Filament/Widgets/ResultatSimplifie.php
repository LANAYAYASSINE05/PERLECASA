<?php

namespace App\Filament\Widgets;

use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ventes encaissées moins dépenses depuis le début, et la quote-part de l'associé selon sa part des apports. */
class ResultatSimplifie extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Résultat de la société';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{ventes: float, depenses: float, resultat: float, quotePart: float} */
    public static function chiffres(): array
    {
        $ventes = (float) Encaissement::where('type', TypeEncaissement::VenteAppartement)->sum('montant');
        $depenses = (float) Decaissement::where('type', '!=', TypeDecaissement::RemboursementAssocie)->sum('montant');
        $resultat = $ventes - $depenses;

        return [
            'ventes' => $ventes,
            'depenses' => $depenses,
            'resultat' => $resultat,
            'quotePart' => round($resultat * auth()->user()->partDuCapital() / 100, 2),
        ];
    }

    protected function getStats(): array
    {
        $c = self::chiffres();

        return [
            Stat::make('Ventes encaissées', Montant::mad($c['ventes']))
                ->descriptionIcon('heroicon-m-home-modern')->description('depuis le début')->color('success'),
            Stat::make('Dépenses', Montant::mad($c['depenses']))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->description('hors remboursements d’associés')->color('danger'),
            Stat::make('Résultat', Montant::mad($c['resultat']))
                ->descriptionIcon('heroicon-m-scale')->color($c['resultat'] >= 0 ? 'primary' : 'danger'),
            Stat::make('Votre quote-part', Montant::mad($c['quotePart']))
                ->descriptionIcon('heroicon-m-chart-pie')->description('selon votre part du capital')->color('warning'),
        ];
    }
}
