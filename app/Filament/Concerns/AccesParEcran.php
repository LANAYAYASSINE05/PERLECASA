<?php

namespace App\Filament\Concerns;

/** La ressource déclare `protected static string $ecran` ; l'accès suit User::accede. */
trait AccesParEcran
{
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->accede(static::$ecran);
    }
}
