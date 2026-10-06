<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/** Avatar généré localement (aucun appel à un service externe) : initiales dorées sur fond marine. */
class AvatarInitiales implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initiales = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#0c2a4d"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#e2c27a" '
            .'font-family="Inter, Arial, sans-serif" font-size="26" font-weight="600">'.e($initiales ?: '?').'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
