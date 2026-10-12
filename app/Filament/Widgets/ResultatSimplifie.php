<?php

namespace App\Filament\Widgets;

use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Support\Montant;
use App\Support\Periode;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ventes encaissées moins dépenses, et la quote-part de l'associé selon sa part des apports. */
class ResultatSimplifie extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Résultat simplifié';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{ventes: float, depenses: float, resultat: float, quotePart: float} */
    public static function chiffres(?Periode $periode = null): array
    {
        $periode ??= Periode::depuisFiltres(null);
        $ventes = (float) $periode->filtrer(Encaissement::where('type', TypeEncaissement::VenteAppartement))->sum('montant');
        $depenses = (float) $periode->filtrer(Decaissement::where('type', '!=', TypeDecaissement::RemboursementAssocie))->sum('montant');
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
        $periode = Periode::depuisFiltres($this->filters);
        $c = self::chiffres($periode);

        return [
            Stat::make('Ventes encaissées', Montant::mad($c['ventes']))
                ->description('Montants reçus · '.mb_strtolower($periode->libelle()))
                ->descriptionIcon('heroicon-m-home-modern')->color('success'),
            Stat::make('Dépenses', Montant::mad($c['depenses']))
                ->description('Charges fixes, fournisseurs, caisse')
                ->descriptionIcon('heroicon-m-arrow-up-tray')->color('danger'),
            Stat::make('Résultat', Montant::mad($c['resultat']))
                ->description('Ventes encaissées moins dépenses')
                ->descriptionIcon('heroicon-m-scale')->color($c['resultat'] >= 0 ? 'primary' : 'danger'),
            Stat::make('Votre quote-part', Montant::mad($c['quotePart']))
                ->description('Selon votre part du capital apporté')
                ->descriptionIcon('heroicon-m-chart-pie')->color('warning'),
        ];
    }
}
