<?php

namespace App\Models;

use App\Enums\StatutBonCommande;
use App\Services\Alertes;
use App\Services\Numerotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonCommande extends Model
{
    protected $table = 'bons_commande';

    protected $fillable = [
        'fournisseur_id', 'date_commande', 'date_livraison', 'objet', 'lieu_livraison', 'conditions_paiement', 'taux_tva', 'statut',
    ];

    protected $attributes = ['taux_tva' => 20, 'statut' => 'en_cours'];

    protected function casts(): array
    {
        return [
            'date_commande' => 'date',
            'date_livraison' => 'date',
            'taux_tva' => 'decimal:2',
            'statut' => StatutBonCommande::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BonCommande $bon): void {
            $bon->numero = Numerotation::suivant('BC');
            $bon->cree_par ??= auth()->id();
        });

        static::created(fn (BonCommande $bon) => Alertes::bonCommandeCree($bon));
        static::updated(fn (BonCommande $bon) => Alertes::bonCommandeModifie($bon));
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class)->orderBy('ordre');
    }

    public function totalHt(): float
    {
        return round($this->lignes->sum(fn (LigneBonCommande $l) => $l->montant()), 2);
    }

    public function totalTva(): float
    {
        return round($this->totalHt() * (float) $this->taux_tva / 100, 2);
    }

    public function totalTtc(): float
    {
        return round($this->totalHt() + $this->totalTva(), 2);
    }
}
