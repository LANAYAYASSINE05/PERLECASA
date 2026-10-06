<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    case Administrateur = 'admin';
    case Comptable = 'comptable';
    case Caissier = 'caissier';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::Comptable => 'Comptable',
            self::Caissier => 'Opérateur de caisse',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Administrateur => 'primary',
            self::Comptable => 'info',
            self::Caissier => 'warning',
        };
    }
}
