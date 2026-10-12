<?php

namespace App\Filament\Support;

use App\Support\Periode;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/** Sélecteur de période en haut d'une page de l'espace associé, transmis à ses widgets. */
trait FiltrePeriodeAssocie
{
    use HasFiltersForm;

    protected function periodeParDefaut(): string
    {
        return 'tout';
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('periode')->label('Période')
                ->options(Periode::OPTIONS)
                ->default($this->periodeParDefaut())
                ->selectablePlaceholder(false),
        ]);
    }

    public function getWidgetData(): array
    {
        return ['filters' => $this->filters];
    }
}
