<?php

namespace App\Filament\Widgets;

use App\Enums\CategorieCharge;
use App\Enums\TypeDecaissement;
use App\Models\Decaissement;
use App\Support\Periode;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Dépenses de la période par catégorie : chaque catégorie de charge fixe, fournisseurs, caisse. */
class RepartitionDepenses extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Répartition des dépenses';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    private const COULEURS = ['#0c2a4d', '#c4953a', '#10b981', '#f43f5e', '#0ea5e9', '#8b5cf6', '#f59e0b', '#64748b', '#14b8a6', '#ec4899'];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array<string, float> montant par catégorie, du plus grand au plus petit */
    public static function repartition(?Periode $periode = null): array
    {
        $periode ??= Periode::depuisFiltres(null);

        return $periode->filtrer(Decaissement::query()->where('type', '!=', TypeDecaissement::RemboursementAssocie))
            ->with('chargeFixe')
            ->get()
            ->groupBy(fn (Decaissement $d) => $d->type === TypeDecaissement::ChargeFixe
                ? ($d->chargeFixe?->categorie ?? CategorieCharge::Autre)->getLabel()
                : $d->type->getLabel())
            ->map(fn ($lignes) => round((float) $lignes->sum('montant'), 2))
            ->sortDesc()
            ->all();
    }

    protected function getData(): array
    {
        $repartition = self::repartition(Periode::depuisFiltres($this->filters));

        return [
            'datasets' => [[
                'label' => 'Dépenses',
                'data' => array_values($repartition),
                'backgroundColor' => array_slice(array_merge(self::COULEURS, self::COULEURS), 0, count($repartition)),
            ]],
            'labels' => array_keys($repartition),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
