<?php

namespace App\Filament\Support;

use Closure;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Pièces acceptées, contrôlées sur leur contenu réel : l'extension et le type annoncé par le navigateur ne suffisent pas. */
class PieceJustificative
{
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const TAILLE_MAX_KO = 5120;

    public static function typeReel(UploadedFile $fichier): ?string
    {
        $chemin = $fichier->getRealPath();

        return $chemin ? (new finfo(FILEINFO_MIME_TYPE))->file($chemin) ?: null : null;
    }

    public static function regleContenu(): Closure
    {
        return function (string $attribut, $fichier, Closure $echec): void {
            if ($fichier instanceof UploadedFile && ! array_key_exists((string) self::typeReel($fichier), self::TYPES)) {
                $echec('Le contenu du fichier n’est pas un PDF ou une image (JPEG, PNG, WEBP).');
            }
        };
    }

    public static function nomStockage(UploadedFile $fichier): string
    {
        return Str::ulid().'.'.self::TYPES[self::typeReel($fichier)];
    }
}
