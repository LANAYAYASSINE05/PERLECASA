<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class FiltrePeriode
{
    public static function make(string $colonne): Filter
    {
        return Filter::make('periode')
            ->form([
                DatePicker::make('du')->label('Du'),
                DatePicker::make('au')->label('Au'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['du'] ?? null, fn ($q, $date) => $q->whereDate($colonne, '>=', $date))
                ->when($data['au'] ?? null, fn ($q, $date) => $q->whereDate($colonne, '<=', $date)))
            ->indicateUsing(function (array $data): array {
                return array_filter([
                    ($data['du'] ?? null) ? 'Du '.date('d/m/Y', strtotime($data['du'])) : null,
                    ($data['au'] ?? null) ? 'Au '.date('d/m/Y', strtotime($data['au'])) : null,
                ]);
            });
    }
}
