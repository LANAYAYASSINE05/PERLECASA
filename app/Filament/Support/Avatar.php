<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Pastille ronde à initiales suivie du nom (style .pc-avatar du thème). */
class Avatar
{
    public static function html(?string $nom, ?string $detail = null): HtmlString
    {
        $nom = trim((string) $nom);

        if ($nom === '') {
            return new HtmlString('—');
        }

        $mots = preg_split('/\s+/u', Str::ascii($nom)) ?: [];
        $initiales = Str::upper(collect($mots)->filter()->take(2)->map(fn ($m) => Str::substr($m, 0, 1))->implode(''));
        $teinte = crc32($nom) % 360;

        return new HtmlString(
            '<span class="pc-avatar-ctn">'
            .'<span class="pc-avatar" style="--pc-teinte: '.$teinte.'" aria-hidden="true">'.e($initiales).'</span>'
            .'<span><span class="pc-avatar-nom">'.e($nom).'</span>'
            .($detail ? '<span class="pc-avatar-detail">'.e($detail).'</span>' : '')
            .'</span></span>'
        );
    }
}
