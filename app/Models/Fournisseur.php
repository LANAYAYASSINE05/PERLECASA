<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends Model
{
    protected $fillable = ['raison_sociale', 'ice', 'telephone', 'email', 'adresse'];

    public function decaissements(): HasMany
    {
        return $this->hasMany(Decaissement::class);
    }

    public function chargesFixes(): HasMany
    {
        return $this->hasMany(ChargeFixe::class);
    }

    public function bonsCommande(): HasMany
    {
        return $this->hasMany(BonCommande::class);
    }

    public function code(): string
    {
        return 'FR'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
