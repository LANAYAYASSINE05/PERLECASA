<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TypeEncaissement: string implements HasColor, HasIcon, HasLabel
{
    case VenteAppartement = 'vente_appartement';
    case VirementRecu = 'virement_recu';
    case ApportAssocie = 'apport_associe';

    public function getLabel(): string
    {
        return match ($this) {
            self::VenteAppartement => "Vente d'appartement",
            self::VirementRecu => 'Virement reçu',
            self::ApportAssocie => 'Apport associé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::VenteAppartement => 'success',
            self::VirementRecu => 'info',
            self::ApportAssocie => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::VenteAppartement => 'heroicon-m-home-modern',
            self::VirementRecu => 'heroicon-m-arrows-right-left',
            self::ApportAssocie => 'heroicon-m-user-group',
        };
    }
}
