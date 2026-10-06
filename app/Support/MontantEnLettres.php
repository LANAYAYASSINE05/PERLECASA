<?php

namespace App\Support;

use NumberFormatter;

class MontantEnLettres
{
    /** 138.5 → « CENT TRENTE-HUIT DIRHAMS ET CINQUANTE CENTIMES » */
    public static function dirhams(float $montant): string
    {
        $centimesTotal = (int) round(abs($montant) * 100);
        $dirhams = intdiv($centimesTotal, 100);
        $centimes = $centimesTotal % 100;
        $lettres = new NumberFormatter('fr', NumberFormatter::SPELLOUT);

        $texte = $lettres->format($dirhams).' '.($dirhams > 1 ? 'dirhams' : 'dirham');

        if ($centimes > 0) {
            $texte .= ' et '.$lettres->format($centimes).' '.($centimes > 1 ? 'centimes' : 'centime');
        }

        return mb_strtoupper($texte);
    }
}
