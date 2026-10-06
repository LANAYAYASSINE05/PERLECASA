<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Periodicite: string implements HasLabel
{
    case Mensuelle = 'mensuelle';
    case Trimestrielle = 'trimestrielle';
    case Annuelle = 'annuelle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mensuelle => 'Mensuelle',
            self::Trimestrielle => 'Trimestrielle',
            self::Annuelle => 'Annuelle',
        };
    }

    public function enMois(): int
    {
        return match ($this) {
            self::Mensuelle => 1,
            self::Trimestrielle => 3,
            self::Annuelle => 12,
        };
    }
}
