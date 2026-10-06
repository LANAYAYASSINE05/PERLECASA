<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Numerotation
{
    /** Numéro suivant au format PREFIXE-AAAA-NNNN, compteur remis à zéro chaque année. */
    public static function suivant(string $prefixe, ?int $annee = null): string
    {
        $annee ??= (int) now()->year;

        return DB::transaction(function () use ($prefixe, $annee): string {
            DB::table('compteurs')->insertOrIgnore(['prefixe' => $prefixe, 'annee' => $annee, 'valeur' => 0]);

            $valeur = DB::table('compteurs')
                ->where(['prefixe' => $prefixe, 'annee' => $annee])
                ->lockForUpdate()
                ->value('valeur') + 1;

            DB::table('compteurs')->where(['prefixe' => $prefixe, 'annee' => $annee])->update(['valeur' => $valeur]);

            return sprintf('%s-%d-%04d', $prefixe, $annee, $valeur);
        });
    }
}
