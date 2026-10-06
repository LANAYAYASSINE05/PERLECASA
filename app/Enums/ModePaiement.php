<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModePaiement: string implements HasLabel
{
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Especes = 'especes';
    case Versement = 'versement';
    case Prelevement = 'prelevement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Virement => 'Virement',
            self::Cheque => 'Chèque',
            self::Especes => 'Espèces',
            self::Versement => 'Versement bancaire',
            self::Prelevement => 'Prélèvement',
        };
    }
}
