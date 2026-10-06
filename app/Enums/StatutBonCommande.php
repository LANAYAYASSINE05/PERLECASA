<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutBonCommande: string implements HasColor, HasLabel
{
    case EnCours = 'en_cours';
    case Livre = 'livre';
    case Annule = 'annule';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Livre => 'Livré',
            self::Annule => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnCours => 'warning',
            self::Livre => 'success',
            self::Annule => 'gray',
        };
    }
}
