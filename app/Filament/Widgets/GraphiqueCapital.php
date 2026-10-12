<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

/** Part de chaque associé dans le capital net investi. */
class GraphiqueCapital extends ChartWidget
{
    protected static ?string $heading = 'Répartition du capital';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    private const COULEURS = ['#0c2a4d', '#c4953a', '#10b981', '#0ea5e9', '#8b5cf6', '#f43f5e', '#f59e0b', '#64748b'];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

    /** @return array<string, float> part (%) par associé, du plus grand au plus petit */
    public static function parts(): array
    {
        return User::query()->associesAvecSoldes()->get()
            ->filter(fn (User $u) => $u->netCharge() > 0)
            ->mapWithKeys(fn (User $u) => [$u->name => User::part($u->netCharge())])
            ->sortDesc()
            ->all();
    }

    protected function getData(): array
    {
        $parts = self::parts();

        return [
            'datasets' => [[
                'label' => 'Part (%)',
                'data' => array_values($parts),
                'backgroundColor' => array_slice(array_merge(self::COULEURS, self::COULEURS, self::COULEURS), 0, count($parts)),
            ]],
            'labels' => array_keys($parts),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
