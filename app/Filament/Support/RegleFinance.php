<?php

namespace App\Filament\Support;

use App\Services\ControleFinance;
use Closure;
use Filament\Forms\Get;

/**
 * Branche ControleFinance sur un champ de formulaire : le champ échoue avec
 * le message que la règle de gestion renvoie pour lui.
 */
class RegleFinance
{
    /** @param  Closure(Get, mixed): array{0: array, 1: ?int}  $donnees  données de l'opération et id à ignorer */
    public static function encaissement(string $champ, Closure $donnees): Closure
    {
        return self::regle('encaissement', $champ, $donnees);
    }

    /** @param  Closure(Get, mixed): array{0: array, 1: ?int}  $donnees */
    public static function decaissement(string $champ, Closure $donnees): Closure
    {
        return self::regle('decaissement', $champ, $donnees);
    }

    private static function regle(string $controle, string $champ, Closure $donnees): Closure
    {
        return fn (Get $get, $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($controle, $champ, $donnees, $get, $record): void {
            [$donneesOperation, $ignorer] = $donnees($get, $record);
            $erreurs = ControleFinance::{$controle}([...$donneesOperation, $champ => $value], $ignorer);

            if (isset($erreurs[$champ])) {
                $fail($erreurs[$champ]);
            }
        };
    }
}
