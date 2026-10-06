<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends Model
{
    protected $table = 'lignes_bon_commande';

    public $timestamps = false;

    protected $fillable = ['reference', 'designation', 'quantite', 'prix_unitaire', 'ordre'];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
        ];
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function montant(): float
    {
        return round((float) $this->quantite * (float) $this->prix_unitaire, 2);
    }
}
