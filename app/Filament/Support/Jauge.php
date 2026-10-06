<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/** Jauge graduée façon règle d'architecte (style .pc-jauge du thème). */
class Jauge
{
    public static function html(float $pourcentage): HtmlString
    {
        $taux = max(0, min(100, round($pourcentage)));
        $etat = $taux >= 100 ? 'pc-jauge-pleine' : '';

        return new HtmlString(
            '<span class="pc-jauge-ctn '.$etat.'" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$taux.'">'
            .'<span class="pc-jauge" style="--pc-taux: '.$taux.'%"><span></span></span>'
            .'<span class="pc-jauge-texte">'.$taux.' %</span>'
            .'</span>'
        );
    }
}
