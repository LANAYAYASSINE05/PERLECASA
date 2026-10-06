<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TypeDecaissement: string implements HasColor, HasIcon, HasLabel
{
    case ChargeFixe = 'charge_fixe';
    case Fournisseur = 'fournisseur';
    case OperationCaisse = 'operation_caisse';

    public function getLabel(): string
    {
        return match ($this) {
            self::ChargeFixe => 'Charge fixe',
            self::Fournisseur => 'Fournisseur',
            self::OperationCaisse => 'Opération de caisse',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ChargeFixe => 'primary',
            self::Fournisseur => 'info',
            self::OperationCaisse => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ChargeFixe => 'heroicon-m-calendar-days',
            self::Fournisseur => 'heroicon-m-truck',
            self::OperationCaisse => 'heroicon-m-banknotes',
        };
    }
}
