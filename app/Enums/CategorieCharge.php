<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CategorieCharge: string implements HasLabel
{
    case Loyer = 'loyer';
    case Salaires = 'salaires';
    case Energie = 'energie';
    case Telecom = 'telecom';
    case Assurance = 'assurance';
    case Impots = 'impots';
    case Credit = 'credit';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Loyer => 'Loyer',
            self::Salaires => 'Salaires',
            self::Energie => 'Eau et électricité',
            self::Telecom => 'Téléphone et internet',
            self::Assurance => 'Assurance',
            self::Impots => 'Impôts et taxes',
            self::Credit => 'Échéance de crédit',
            self::Autre => 'Autre',
        };
    }
}
