<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\TypeEncaissement;
use App\Services\Alertes;
use App\Services\ControleFinance;
use App\Services\Numerotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Encaissement extends Model
{
    protected $fillable = [
        'type', 'associe_id', 'date_operation', 'vente_id', 'emetteur', 'compte_id', 'mode', 'reference', 'montant', 'libelle',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeEncaissement::class,
            'mode' => ModePaiement::class,
            'date_operation' => 'date',
            'montant' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Encaissement $e): void {
            if ($e->type !== TypeEncaissement::VenteAppartement) {
                $e->vente_id = null;
            }

            if ($e->type !== TypeEncaissement::ApportAssocie) {
                $e->associe_id = null;
            }

            $erreurs = ControleFinance::encaissement($e->getAttributes(), $e->getKey());

            if ($erreurs) {
                throw ValidationException::withMessages($erreurs);
            }
        });

        static::creating(function (Encaissement $e): void {
            $e->numero = Numerotation::suivant('ENC');
            $e->cree_par ??= auth()->id();
        });

        static::created(function (Encaissement $e): void {
            Alertes::encaissement($e);
            Alertes::investissement($e, 'enregistré');
        });
        static::updated(fn (Encaissement $e) => Alertes::investissement($e, 'modifié'));
        static::deleted(fn (Encaissement $e) => Alertes::investissement($e, 'supprimé'));
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function associe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'associe_id');
    }

    public function provenance(): string
    {
        return match ($this->type) {
            TypeEncaissement::VenteAppartement => (string) $this->vente?->libelle(),
            TypeEncaissement::ApportAssocie => (string) $this->associe?->name,
            default => (string) $this->emetteur,
        };
    }
}
