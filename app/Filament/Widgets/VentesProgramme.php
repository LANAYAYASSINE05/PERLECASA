<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FicheProgramme;
use App\Models\Encaissement;
use App\Models\Vente;
use App\Support\Montant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Chiffre d'affaires vendu, encaissé et reste à encaisser du programme choisi. */
class VentesProgramme extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{vendu: float, encaisse: float, reste: float, prixM2Vendu: float} */
    public static function chiffres(?int $programmeId): array
    {
        $ventes = Vente::query()->whereHas('appartement', fn ($q) => $q->when($programmeId, fn ($q) => $q->where('programme_id', $programmeId)));
        $vendu = (float) (clone $ventes)->sum('prix_vente');
        $surface = (float) (clone $ventes)->join('appartements', 'appartements.id', '=', 'ventes.appartement_id')->sum('appartements.surface');
        $encaisse = (float) Encaissement::whereIn('vente_id', (clone $ventes)->select('ventes.id'))->sum('montant');

        return [
            'vendu' => $vendu,
            'encaisse' => $encaisse,
            'reste' => round($vendu - $encaisse, 2),
            'prixM2Vendu' => $surface > 0 ? round($vendu / $surface, 2) : 0.0,
        ];
    }

    protected function getStats(): array
    {
        $c = self::chiffres(FicheProgramme::programmeChoisi($this->filters));
        $taux = $c['vendu'] > 0 ? round($c['encaisse'] / $c['vendu'] * 100) : 0;

        return [
            Stat::make('Chiffre d’affaires vendu', Montant::mad($c['vendu']))
                ->descriptionIcon('heroicon-m-document-check')->description('prix de vente signés')->color('primary'),
            Stat::make('Encaissé', Montant::mad($c['encaisse']))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->description("{$taux} % du vendu")->color('success'),
            Stat::make('Reste à encaisser', Montant::mad($c['reste']))
                ->descriptionIcon('heroicon-m-clock')->description('sur les ventes signées')->color('warning'),
            Stat::make('Prix moyen vendu au m²', $c['prixM2Vendu'] ? Montant::mad($c['prixM2Vendu']) : '—')
                ->descriptionIcon('heroicon-m-square-3-stack-3d')->description('des lots vendus')->color('gray'),
        ];
    }
}
