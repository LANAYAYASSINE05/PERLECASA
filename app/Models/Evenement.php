<?php

namespace App\Models;

use App\Enums\TypeEvenement;
use App\Services\Alertes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Échéance de l'agenda des associés : AG, réunion, encaissement attendu, étape de chantier. */
class Evenement extends Model
{
    protected $fillable = ['titre', 'type', 'date_debut', 'lieu', 'description', 'montant'];

    protected function casts(): array
    {
        return [
            'type' => TypeEvenement::class,
            'date_debut' => 'datetime',
            'montant' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Evenement $e) => $e->cree_par ??= auth()->id());
        static::saving(function (Evenement $e): void {
            if ($e->type !== TypeEvenement::Encaissement) {
                $e->montant = null;
            }
        });
        static::created(fn (Evenement $e) => Alertes::evenement($e));
    }

    public function scopeAVenir(Builder $query): Builder
    {
        return $query->where('date_debut', '>=', now()->startOfDay());
    }
}
