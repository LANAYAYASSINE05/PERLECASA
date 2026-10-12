<?php

namespace App\Filament\Widgets;

use App\Enums\StatutAppartement;
use App\Filament\Pages\FicheProgramme;
use App\Models\Appartement;
use App\Support\Montant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Lots disponibles et vendus, tous programmes confondus ou pour un seul (fiche programme). */
class ResumeStock extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{lots: int, disponibles: int, vendus: int, valeurStock: float, prixM2: float} */
    public static function chiffres(?int $programmeId = null): array
    {
        $lots = Appartement::query()->when($programmeId, fn ($q) => $q->where('programme_id', $programmeId));
        $disponibles = (clone $lots)->where('statut', StatutAppartement::Disponible);
        $surface = (float) (clone $disponibles)->sum('surface');
        $valeur = (float) (clone $disponibles)->sum('prix');

        return [
            'lots' => (clone $lots)->count(),
            'disponibles' => (clone $disponibles)->count(),
            'vendus' => (clone $lots)->where('statut', StatutAppartement::Vendu)->count(),
            'valeurStock' => $valeur,
            'prixM2' => $surface > 0 ? round($valeur / $surface, 2) : 0.0,
        ];
    }

    protected function getStats(): array
    {
        $c = self::chiffres(FicheProgramme::programmeChoisi($this->filters));
        $taux = $c['lots'] ? round($c['vendus'] / $c['lots'] * 100) : 0;

        return [
            Stat::make('Lots disponibles', $c['disponibles'])->description("sur {$c['lots']} lots")
                ->descriptionIcon('heroicon-m-home')->color('success'),
            Stat::make('Lots vendus', $c['vendus'])->description("{$taux} % commercialisé")
                ->descriptionIcon('heroicon-m-check-badge')->color('primary'),
            Stat::make('Valeur du stock', Montant::mad($c['valeurStock']))->description('au prix catalogue')
                ->descriptionIcon('heroicon-m-banknotes')->color('warning'),
            Stat::make('Prix moyen au m²', $c['prixM2'] ? Montant::mad($c['prixM2']) : '—')->description('des lots disponibles')
                ->descriptionIcon('heroicon-m-square-3-stack-3d')->color('gray'),
        ];
    }
}
