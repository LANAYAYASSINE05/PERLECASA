<?php

namespace App\Models;

use App\Enums\TypeCompte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compte extends Model
{
    protected $fillable = ['nom', 'type', 'banque', 'rib', 'adresse_agence', 'solde_initial', 'actif'];

    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return [
            'type' => TypeCompte::class,
            'solde_initial' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(Encaissement::class);
    }

    public function decaissements(): HasMany
    {
        return $this->hasMany(Decaissement::class);
    }

    /** Solde initial + encaissements - décaissements ; $saufDecaissement ignore une ligne en cours de modification. */
    public function solde(?int $saufDecaissement = null): float
    {
        $entrees = (float) $this->encaissements()->sum('montant');
        $sorties = (float) $this->decaissements()
            ->when($saufDecaissement, fn ($q) => $q->whereKeyNot($saufDecaissement))
            ->sum('montant');

        return round((float) $this->solde_initial + $entrees - $sorties, 2);
    }
}
