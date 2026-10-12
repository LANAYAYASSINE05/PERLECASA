<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\FicheProgramme;
use App\Models\Encaissement;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Encaissements des ventes du programme choisi, mois par mois sur 12 mois. */
class EncaissementsProgramme extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Encaissements sur 12 mois';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array{labels: list<string>, montants: list<float>} */
    public static function serie(?int $programmeId): array
    {
        $mois = collect(range(11, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));

        $montants = Encaissement::query()
            ->join('ventes', 'ventes.id', '=', 'encaissements.vente_id')
            ->join('appartements', 'appartements.id', '=', 'ventes.appartement_id')
            ->when($programmeId, fn ($q) => $q->where('appartements.programme_id', $programmeId))
            ->where('encaissements.date_operation', '>=', $mois->first())
            ->groupBy('mois')
            ->selectRaw("date_format(encaissements.date_operation, '%Y-%m') as mois, sum(encaissements.montant) as total")
            ->toBase()
            ->pluck('total', 'mois');

        return [
            'labels' => $mois->map(fn ($m) => ucfirst($m->translatedFormat('M Y')))->all(),
            'montants' => $mois->map(fn ($m) => round((float) ($montants[$m->format('Y-m')] ?? 0), 2))->all(),
        ];
    }

    protected function getData(): array
    {
        $s = self::serie(FicheProgramme::programmeChoisi($this->filters));

        return [
            'datasets' => [[
                'label' => 'Encaissé',
                'data' => $s['montants'],
                'backgroundColor' => '#10b981',
                'borderRadius' => 4,
            ]],
            'labels' => $s['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
