<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TypeEvenement: string implements HasColor, HasIcon, HasLabel
{
    case AssembleeGenerale = 'ag';
    case Reunion = 'reunion';
    case Encaissement = 'encaissement';
    case Chantier = 'chantier';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::AssembleeGenerale => 'Assemblée générale',
            self::Reunion => 'Réunion',
            self::Encaissement => 'Encaissement attendu',
            self::Chantier => 'Étape de chantier',
            self::Autre => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AssembleeGenerale => 'primary',
            self::Reunion => 'info',
            self::Encaissement => 'success',
            self::Chantier => 'warning',
            self::Autre => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::AssembleeGenerale => 'heroicon-m-building-office-2',
            self::Reunion => 'heroicon-m-users',
            self::Encaissement => 'heroicon-m-banknotes',
            self::Chantier => 'heroicon-m-wrench-screwdriver',
            self::Autre => 'heroicon-m-calendar',
        };
    }
}
