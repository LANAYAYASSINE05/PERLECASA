<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;
use Filament\Actions\Action;

class MesInvestissements extends PageAssocie
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $title = 'Mes investissements';

    protected static ?string $slug = 'mes-investissements';

    protected static ?string $navigationGroup = 'Mon investissement';

    protected static ?int $navigationSort = 1;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('releve')->label('Relevé Excel')
                ->icon('heroicon-o-table-cells')->color('success')
                ->url(fn () => route('associes.releve', auth()->user()))
                ->extraAttributes(['download' => true]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [Widgets\ResumeInvestissements::class];
    }

    protected function getFooterWidgets(): array
    {
        return [Widgets\MesApports::class, Widgets\MesRemboursements::class];
    }
}
