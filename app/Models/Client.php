<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['nom', 'prenom', 'cin', 'ice', 'telephone', 'email', 'adresse'];

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }

    public function code(): string
    {
        return 'CL'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
