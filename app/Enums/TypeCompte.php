<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TypeCompte: string implements HasColor, HasLabel
{
    case Banque = 'banque';
    case Caisse = 'caisse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Banque => 'Banque',
            self::Caisse => 'Caisse',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Banque => 'info',
            self::Caisse => 'warning',
        };
    }
}
