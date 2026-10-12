<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\TypeDecaissement;
use App\Services\Alertes;
use App\Services\ControleFinance;
use App\Services\Numerotation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Decaissement extends Model
{
    public const DISQUE = 'local';

    protected $fillable = [
        'type', 'associe_id', 'date_operation', 'charge_fixe_id', 'periode', 'fournisseur_id', 'reference_facture',
        'operateur', 'beneficiaire', 'compte_id', 'mode', 'montant', 'motif', 'piece_justificative',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeDecaissement::class,
            'mode' => ModePaiement::class,
            'date_operation' => 'date',
            'montant' => 'decimal:2',
            'justifie' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Decaissement $d): void {
            if ($d->type !== TypeDecaissement::ChargeFixe) {
                $d->charge_fixe_id = null;
                $d->periode = null;
            }

            if ($d->type !== TypeDecaissement::Fournisseur) {
                $d->fournisseur_id = null;
                $d->reference_facture = null;
            }

            if ($d->type !== TypeDecaissement::OperationCaisse) {
                $d->operateur = null;
                $d->beneficiaire = null;
            }

            if ($d->type !== TypeDecaissement::RemboursementAssocie) {
                $d->associe_id = null;
            }

            $d->justifie = filled($d->piece_justificative);

            $erreurs = ControleFinance::decaissement($d->getAttributes(), $d->getKey());

            if ($erreurs) {
                throw ValidationException::withMessages($erreurs);
            }
        });

        static::creating(function (Decaissement $d): void {
            $d->numero = Numerotation::suivant('DEC');
            $d->cree_par ??= auth()->id();
        });

        static::created(function (Decaissement $d): void {
            Alertes::decaissementCree($d);
            Alertes::investissement($d, 'enregistré');
        });
        static::updated(function (Decaissement $d): void {
            Alertes::decaissementModifie($d);
            Alertes::investissement($d, 'modifié');
        });

        static::deleted(function (Decaissement $d): void {
            Alertes::investissement($d, 'supprimé');

            if ($d->piece_justificative) {
                Storage::disk(self::DISQUE)->delete($d->piece_justificative);
            }
        });
    }

    public function chargeFixe(): BelongsTo
    {
        return $this->belongsTo(ChargeFixe::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
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

    public function scopeCaisseNonJustifiee(Builder $query): Builder
    {
        return $query->where('type', TypeDecaissement::OperationCaisse)->where('justifie', false);
    }

    public function destinataire(): string
    {
        return match ($this->type) {
            TypeDecaissement::ChargeFixe => (string) $this->chargeFixe?->libelle.($this->periode ? " · {$this->periode}" : ''),
            TypeDecaissement::Fournisseur => (string) $this->fournisseur?->raison_sociale,
            TypeDecaissement::OperationCaisse => trim("{$this->operateur} · {$this->motif}", ' ·'),
            TypeDecaissement::RemboursementAssocie => (string) $this->associe?->name,
        };
    }
}
