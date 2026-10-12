<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CategorieDocument: string implements HasColor, HasLabel
{
    case Statuts = 'statuts';
    case ProcesVerbal = 'pv_ag';
    case Bilan = 'bilan';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Statuts => 'Statuts',
            self::ProcesVerbal => 'PV d’assemblée générale',
            self::Bilan => 'Bilan et comptes annuels',
            self::Autre => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Statuts => 'primary',
            self::ProcesVerbal => 'info',
            self::Bilan => 'success',
            self::Autre => 'gray',
        };
    }
}
