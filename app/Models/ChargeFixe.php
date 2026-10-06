<?php

namespace App\Models;

use App\Enums\CategorieCharge;
use App\Enums\Periodicite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ChargeFixe extends Model
{
    protected $table = 'charges_fixes';

    protected $fillable = ['libelle', 'categorie', 'montant', 'periodicite', 'fournisseur_id', 'actif'];

    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieCharge::class,
            'periodicite' => Periodicite::class,
            'montant' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function decaissements(): HasMany
    {
        return $this->hasMany(Decaissement::class);
    }

    public function dernierePeriodePayee(): ?string
    {
        return $this->decaissements()->max('periode');
    }

    /** Période (AAAA-MM) à régler ensuite : celle qui suit le dernier paiement, ou le mois en cours. */
    public function prochainePeriode(): string
    {
        $derniere = $this->dernierePeriodePayee();

        return $derniere
            ? Carbon::createFromFormat('Y-m', $derniere)->startOfMonth()->addMonths($this->periodicite->enMois())->format('Y-m')
            : now()->format('Y-m');
    }

    public function estEnRetard(): bool
    {
        return $this->actif && $this->prochainePeriode() < now()->format('Y-m');
    }
}
