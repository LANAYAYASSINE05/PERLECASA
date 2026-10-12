<?php

namespace App\Filament\Widgets;

use App\Models\Decaissement;
use App\Models\Encaissement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FluxMensuels extends ChartWidget
{
    protected static ?string $heading = 'Encaissements et décaissements — 6 derniers mois';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('encaissements');
    }

    protected function getData(): array
    {
        $mois = collect(range(5, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));
        $depuis = $mois->first();

        $parMois = fn (string $modele) => $modele::query()
            ->where('date_operation', '>=', $depuis)
            ->groupBy('mois')
            ->pluck(DB::raw('sum(montant)'), DB::raw("date_format(date_operation, '%Y-%m') as mois"));

        $entrees = $parMois(Encaissement::class);
        $sorties = $parMois(Decaissement::class);

        return [
            'datasets' => [
                [
                    'label' => 'Encaissements',
                    'data' => $mois->map(fn ($m) => round((float) ($entrees[$m->format('Y-m')] ?? 0), 2))->all(),
                    'backgroundColor' => '#10b981',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Décaissements',
                    'data' => $mois->map(fn ($m) => round((float) ($sorties[$m->format('Y-m')] ?? 0), 2))->all(),
                    'backgroundColor' => '#f43f5e',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $mois->map(fn ($m) => ucfirst($m->translatedFormat('M Y')))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
