<?php

namespace App\Filament\Widgets;

use App\Models\Compte;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Support\Montant;
use App\Support\Periode;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Trésorerie de la société en lecture seule, pour l'associé ; les flux suivent la période choisie (ce mois par défaut). */
class SyntheseTresorerie extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Trésorerie de la société';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    protected function getStats(): array
    {
        $periode = Periode::depuisFiltres($this->filters, 'mois');
        $comptes = Compte::where('actif', true)->orderBy('type')->orderBy('nom')->get();

        return [
            Stat::make('Trésorerie disponible', Montant::mad($comptes->sum(fn (Compte $c) => $c->solde())))
                ->description('Banques et caisses, à ce jour')
                ->descriptionIcon('heroicon-m-building-library')->color('primary'),
            Stat::make('Encaissé · '.mb_strtolower($periode->libelle()), Montant::mad((float) $periode->filtrer(Encaissement::query())->sum('montant')))
                ->descriptionIcon('heroicon-m-arrow-down-tray')->description('Toutes origines')->color('success'),
            Stat::make('Décaissé · '.mb_strtolower($periode->libelle()), Montant::mad((float) $periode->filtrer(Decaissement::query())->sum('montant')))
                ->descriptionIcon('heroicon-m-arrow-up-tray')->description('Toutes natures')->color('danger'),
            ...$comptes->map(fn (Compte $c) => Stat::make($c->nom, Montant::mad($c->solde()))
                ->description($c->type->getLabel())->color('gray'))->all(),
        ];
    }
}
