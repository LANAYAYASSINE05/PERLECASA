<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Période des cartes du tableau de bord : un jour, la semaine ou le mois, du début à la fin incluses. */
final class PeriodeTableau
{
    public const OPTIONS = [
        'aujourdhui' => 'Aujourd’hui',
        'hier' => 'Hier',
        'semaine' => 'Cette semaine',
        'mois' => 'Ce mois',
        'date' => 'Une date précise',
    ];

    private function __construct(
        public readonly string $cle,
        public readonly Carbon $debut,
        public readonly Carbon $fin,
    ) {}

    public static function depuisFiltres(?array $filtres): self
    {
        $cle = $filtres['periode'] ?? 'aujourdhui';
        $date = rescue(fn () => filled($filtres['date'] ?? null) ? Carbon::parse($filtres['date']) : null, null, false);

        return match ($cle) {
            'hier' => new self($cle, today()->subDay(), today()->subDay()),
            'semaine' => new self($cle, today()->startOfWeek(), today()->endOfWeek()->startOfDay()),
            'mois' => new self($cle, today()->startOfMonth(), today()->endOfMonth()->startOfDay()),
            'date' => $date && $date->lte(today())
                ? new self($cle, $date->startOfDay(), $date->copy()->startOfDay())
                : new self('aujourdhui', today(), today()),
            default => new self('aujourdhui', today(), today()),
        };
    }

    public function unJour(): bool
    {
        return $this->debut->isSameDay($this->fin);
    }

    public function titre(): string
    {
        $jour = fn (Carbon $d) => $d->locale('fr')->isoFormat('dddd D MMMM');

        return match ($this->cle) {
            'aujourdhui' => 'Aujourd’hui · '.$jour($this->debut),
            'hier' => 'Hier · '.$jour($this->debut),
            'semaine' => 'Cette semaine · du '.$this->debut->locale('fr')->isoFormat('D MMMM').' au '.$this->fin->locale('fr')->isoFormat('D MMMM'),
            'mois' => 'Ce mois · '.$this->debut->locale('fr')->isoFormat('MMMM YYYY'),
            default => ucfirst($this->debut->locale('fr')->isoFormat('dddd D MMMM YYYY')),
        };
    }

    /** Suffixe des libellés de cartes : « aujourd’hui », « hier », « cette semaine »… */
    public function suffixe(): string
    {
        return match ($this->cle) {
            'aujourdhui' => 'aujourd’hui',
            'hier' => 'hier',
            'semaine' => 'cette semaine',
            'mois' => 'ce mois',
            default => 'le '.$this->debut->format('d/m/Y'),
        };
    }
}
