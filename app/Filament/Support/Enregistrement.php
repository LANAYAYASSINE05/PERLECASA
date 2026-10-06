<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Enregistrement
{
    /**
     * Exécute l'écriture en transaction ; une règle de gestion refusée par un modèle
     * devient une notification et laisse la fenêtre ouverte.
     */
    public static function proteger(Closure $ecriture): mixed
    {
        try {
            return DB::transaction($ecriture);
        } catch (ValidationException $e) {
            Notification::make()
                ->danger()
                ->title('Opération refusée')
                ->body(collect($e->errors())->flatten()->first())
                ->persistent()
                ->send();

            throw new Halt;
        }
    }
}
