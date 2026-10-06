<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutAppartement: string implements HasColor, HasLabel
{
    case Disponible = 'disponible';
    case Vendu = 'vendu';

    public function getLabel(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Vendu => 'Vendu',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Disponible => 'success',
            self::Vendu => 'gray',
        };
    }
}
