<?php

namespace App\Models;

use App\Enums\StatutDemande;
use App\Enums\TypeDemande;
use App\Services\Alertes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande adressée par un associé à l'administrateur, qui l'accepte ou la refuse avec une réponse. */
class DemandeAssocie extends Model
{
    protected $table = 'demandes_associes';

    protected $fillable = ['type', 'objet', 'message', 'montant'];

    protected $attributes = ['statut' => 'en_attente'];

    protected function casts(): array
    {
        return [
            'type' => TypeDemande::class,
            'statut' => StatutDemande::class,
            'montant' => 'decimal:2',
            'traite_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (DemandeAssocie $d) => $d->associe_id ??= auth()->id());
        static::created(fn (DemandeAssocie $d) => Alertes::demandeAssocie($d));

        static::updated(function (DemandeAssocie $d): void {
            if ($d->wasChanged('statut') && $d->statut !== StatutDemande::EnAttente) {
                Alertes::reponseDemande($d);
            }
        });
    }

    public function associe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'associe_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /** Clôture la demande : seul l'administrateur appelle cette méthode, depuis sa ressource. */
    public function traiter(StatutDemande $statut, ?string $reponse): void
    {
        $this->forceFill([
            'statut' => $statut,
            'reponse' => $reponse,
            'traite_par' => auth()->id(),
            'traite_le' => now(),
        ])->save();
    }
}
