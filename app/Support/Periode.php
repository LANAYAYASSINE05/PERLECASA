<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Période choisie dans le filtre des pages de l'espace associé. */
final class Periode
{
    public const OPTIONS = [
        'mois' => 'Ce mois',
        'trimestre' => 'Ce trimestre',
        'annee' => 'Cette année',
        'douze_mois' => '12 derniers mois',
        'tout' => 'Depuis le début',
    ];

    private function __construct(public readonly string $cle, public readonly ?Carbon $debut) {}

    public static function depuisFiltres(?array $filtres, string $parDefaut = 'tout'): self
    {
        $cle = $filtres['periode'] ?? null;
        $cle = isset(self::OPTIONS[$cle]) ? $cle : $parDefaut;

        return new self($cle, match ($cle) {
            'mois' => now()->startOfMonth(),
            'trimestre' => now()->startOfQuarter(),
            'annee' => now()->startOfYear(),
            'douze_mois' => now()->startOfMonth()->subMonths(11),
            default => null,
        });
    }

    public function libelle(): string
    {
        return self::OPTIONS[$this->cle];
    }

    public function filtrer(Builder $query, string $colonne = 'date_operation'): Builder
    {
        return $query->when($this->debut, fn ($q, Carbon $debut) => $q->whereDate($colonne, '>=', $debut));
    }
}
