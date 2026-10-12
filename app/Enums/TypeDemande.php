<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TypeDemande: string implements HasIcon, HasLabel
{
    case Remboursement = 'remboursement';
    case Information = 'information';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Remboursement => 'Demande de remboursement',
            self::Information => 'Demande d’information',
            self::Autre => 'Autre',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Remboursement => 'heroicon-m-banknotes',
            self::Information => 'heroicon-m-question-mark-circle',
            self::Autre => 'heroicon-m-chat-bubble-left-ellipsis',
        };
    }
}
