<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Actions\Action;
use Filament\Pages\Page;

class MesInvestissements extends Page
{
    protected static string $view = 'filament.pages.mes-investissements';

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $title = 'Mes investissements';

    protected static ?string $slug = 'mes-investissements';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->accede('investissements');
    }

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
        return [Widgets\ResumeInvestissements::class, Widgets\ResultatSimplifie::class, Widgets\SyntheseTresorerie::class];
    }

    protected function getFooterWidgets(): array
    {
        return [Widgets\SyntheseProgrammes::class, Widgets\MesApports::class, Widgets\MesRemboursements::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
