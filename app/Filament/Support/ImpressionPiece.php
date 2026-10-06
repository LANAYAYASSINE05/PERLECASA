<?php

namespace App\Filament\Support;

use Closure;
use Filament\Tables\Actions\Action;

/** Téléchargement Excel d'une pièce commerciale (facture, reçu, bon de commande). */
class ImpressionPiece
{
    public static function make(string $libelle, Closure $url): Action
    {
        return Action::make('telecharger_excel')->label($libelle)
            ->icon('heroicon-o-table-cells')->color('success')
            ->url(fn ($record) => $url($record))
            ->extraAttributes(['download' => true]);
    }
}
