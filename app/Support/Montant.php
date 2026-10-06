<?php

namespace App\Support;

class Montant
{
    public static function mad(float|int|string|null $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ').' MAD';
    }
}
