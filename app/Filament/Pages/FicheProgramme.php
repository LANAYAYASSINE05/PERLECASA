<?php

namespace App\Filament\Pages;

use App\Filament\Support\PageAssocie;
use App\Filament\Widgets;
use App\Models\Programme;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/** Détail d'un programme : ventes, encaissements et lots. Les dépenses ne sont pas rattachées aux programmes. */
class FicheProgramme extends PageAssocie
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Programmes immobiliers';

    protected static ?string $title = 'Fiche programme';

    protected static ?string $slug = 'espace-associe/fiche-programme';

    protected static ?int $navigationSort = 2;

    public static function programmeChoisi(?array $filtres): ?int
    {
        $id = $filtres['programme'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public static function urlPour(Programme $programme): string
    {
        return self::getUrl(['filters' => ['programme' => $programme->id]]);
    }

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('programme')->label('Programme')
                ->options(fn () => Programme::orderBy('nom')->pluck('nom', 'id'))
                ->default(fn () => Programme::orderBy('nom')->value('id'))
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    public function getSubheading(): ?string
    {
        $programme = Programme::find(self::programmeChoisi($this->filters));

        return $programme ? trim("{$programme->nom} · {$programme->ville}", ' ·') : null;
    }

    public function getWidgetData(): array
    {
        return ['filters' => $this->filters];
    }

    protected function getFooterWidgets(): array
    {
        return [
            Widgets\VentesProgramme::class,
            Widgets\ResumeStock::class,
            Widgets\EncaissementsProgramme::class,
            Widgets\LotsProgramme::class,
        ];
    }
}
