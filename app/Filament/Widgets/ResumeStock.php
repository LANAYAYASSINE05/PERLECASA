<?php

namespace App\Filament\Widgets;

use App\Enums\StatutAppartement;
use App\Models\Appartement;
use App\Support\Montant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Lots disponibles et vendus, tous programmes confondus. */
class ResumeStock extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{lots: int, disponibles: int, vendus: int, valeurStock: float} */
    public static function chiffres(): array
    {
        $disponibles = Appartement::where('statut', StatutAppartement::Disponible);

        return [
            'lots' => Appartement::count(),
            'disponibles' => (clone $disponibles)->count(),
            'vendus' => Appartement::where('statut', StatutAppartement::Vendu)->count(),
            'valeurStock' => (float) (clone $disponibles)->sum('prix'),
        ];
    }

    protected function getStats(): array
    {
        $c = self::chiffres();
        $taux = $c['lots'] ? round($c['vendus'] / $c['lots'] * 100) : 0;

        return [
            Stat::make('Lots disponibles', $c['disponibles'])->description("sur {$c['lots']} lots")
                ->descriptionIcon('heroicon-m-home')->color('success'),
            Stat::make('Lots vendus', $c['vendus'])->description("{$taux} % commercialisé")
                ->descriptionIcon('heroicon-m-check-badge')->color('primary'),
            Stat::make('Valeur du stock', Montant::mad($c['valeurStock']))->description('au prix catalogue')
                ->descriptionIcon('heroicon-m-banknotes')->color('warning'),
        ];
    }
}
