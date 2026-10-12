<?php

namespace App\Filament\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** Documents partagés avec les associés, contrôlés sur leur contenu réel comme les pièces justificatives. */
class DocumentPartage
{
    public const TYPES = [
        ...PieceJustificative::TYPES,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    public const TAILLE_MAX_KO = 10240;

    public static function regleContenu(): Closure
    {
        return function (string $attribut, $fichier, Closure $echec): void {
            if ($fichier instanceof UploadedFile && ! array_key_exists((string) PieceJustificative::typeReel($fichier), self::TYPES)) {
                $echec('Le fichier doit être un PDF, une image, un classeur Excel ou un document Word.');
            }
        };
    }

    public static function nomStockage(UploadedFile $fichier): string
    {
        return Str::ulid().'.'.self::TYPES[PieceJustificative::typeReel($fichier)];
    }
}
