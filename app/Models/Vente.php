<?php

namespace App\Models;

use App\Enums\StatutAppartement;
use App\Services\Alertes;
use App\Services\Numerotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Vente extends Model
{
    protected $fillable = ['appartement_id', 'client_id', 'date_vente', 'prix_vente'];

    protected function casts(): array
    {
        return [
            'date_vente' => 'date',
            'prix_vente' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vente $vente): void {
            $appartement = Appartement::findOrFail($vente->appartement_id);

            if ($appartement->statut !== StatutAppartement::Disponible) {
                throw ValidationException::withMessages(['appartement_id' => "L'appartement {$appartement->reference} est déjà vendu."]);
            }

            $vente->numero = Numerotation::suivant('VTE');
        });

        static::created(function (Vente $vente): void {
            $vente->appartement()->update(['statut' => StatutAppartement::Vendu]);
            Alertes::vente($vente);
        });

        static::deleting(function (Vente $vente): void {
            if ($vente->encaissements()->exists()) {
                throw ValidationException::withMessages(['vente' => 'Cette vente a déjà des encaissements : supprimez-les avant de l’annuler.']);
            }
        });

        static::deleted(fn (Vente $vente) => $vente->appartement()->update(['statut' => StatutAppartement::Disponible]));
    }

    public function appartement(): BelongsTo
    {
        return $this->belongsTo(Appartement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function encaissements(): HasMany
    {
        return $this->hasMany(Encaissement::class);
    }

    public function encaisse(?int $sauf = null): float
    {
        return (float) $this->encaissements()->when($sauf, fn ($q) => $q->whereKeyNot($sauf))->sum('montant');
    }

    public function resteDu(?int $sauf = null): float
    {
        return round((float) $this->prix_vente - $this->encaisse($sauf), 2);
    }

    /** Le numéro de facture est attribué à la première impression, puis ne change plus. */
    public function numeroFacture(): string
    {
        if (! $this->numero_facture) {
            $this->forceFill(['numero_facture' => Numerotation::suivant('FAC')])->saveQuietly();
        }

        return $this->numero_facture;
    }

    public function libelle(): string
    {
        return "{$this->numero} · {$this->appartement?->reference} · {$this->client?->nomComplet()}";
    }
}
