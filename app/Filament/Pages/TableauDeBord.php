<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use App\Support\PeriodeTableau;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class TableauDeBord extends Dashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Tableau de bord';

    protected static ?string $navigationLabel = 'Tableau de bord';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->accede('tableau');
    }

    /** Le filtre ne concerne que les cartes de la période ; les autres widgets gardent leur propre échelle. */
    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('periode')->label('Période des cartes')
                ->options(PeriodeTableau::OPTIONS)
                ->default('aujourdhui')
                ->selectablePlaceholder(false)
                ->live(),
            DatePicker::make('date')->label('Date')
                ->native(false)->displayFormat('d/m/Y')
                ->maxDate(today())
                ->default(today())
                ->visible(fn (Get $get) => $get('periode') === 'date'),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            Widgets\BandeauAccueil::class,
            Widgets\ChiffresDuJour::class,
            Widgets\ResumeInvestissements::class,
            Widgets\ResultatSimplifie::class,
            Widgets\ChiffresFinance::class,
            Widgets\FluxMensuels::class,
            Widgets\ChargesEnRetard::class,
            Widgets\CaisseNonJustifiee::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return ['md' => 2];
    }
}
