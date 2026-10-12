<?php

namespace App\Filament\Widgets;

use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use App\Models\Decaissement;
use App\Models\Encaissement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** Ventes encaissées, dépenses et résultat des 12 derniers mois. */
class EvolutionResultat extends ChartWidget
{
    protected static ?string $heading = 'Évolution sur 12 mois';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{labels: list<string>, ventes: list<float>, depenses: list<float>, resultat: list<float>} */
    public static function series(): array
    {
        $mois = collect(range(11, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));
        $depuis = $mois->first();

        $parMois = fn ($query) => $query
            ->where('date_operation', '>=', $depuis)
            ->groupBy('mois')
            ->pluck(DB::raw('sum(montant)'), DB::raw("date_format(date_operation, '%Y-%m') as mois"));

        $ventes = $parMois(Encaissement::where('type', TypeEncaissement::VenteAppartement));
        $depenses = $parMois(Decaissement::where('type', '!=', TypeDecaissement::RemboursementAssocie));
        $valeur = fn ($serie) => $mois->map(fn ($m) => round((float) ($serie[$m->format('Y-m')] ?? 0), 2))->all();

        $v = $valeur($ventes);
        $d = $valeur($depenses);

        return [
            'labels' => $mois->map(fn ($m) => ucfirst($m->translatedFormat('M Y')))->all(),
            'ventes' => $v,
            'depenses' => $d,
            'resultat' => array_map(fn ($a, $b) => round($a - $b, 2), $v, $d),
        ];
    }

    protected function getData(): array
    {
        $s = self::series();

        return [
            'datasets' => [
                ['label' => 'Ventes encaissées', 'data' => $s['ventes'], 'borderColor' => '#10b981', 'backgroundColor' => '#10b98133'],
                ['label' => 'Dépenses', 'data' => $s['depenses'], 'borderColor' => '#f43f5e', 'backgroundColor' => '#f43f5e33'],
                ['label' => 'Résultat', 'data' => $s['resultat'], 'borderColor' => '#c4953a', 'backgroundColor' => '#c4953a33', 'borderDash' => [6, 4]],
            ],
            'labels' => $s['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
