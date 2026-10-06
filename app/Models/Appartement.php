<?php

namespace App\Models;

use App\Enums\StatutAppartement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appartement extends Model
{
    protected $fillable = ['programme_id', 'reference', 'typologie', 'surface', 'prix', 'statut'];

    protected function casts(): array
    {
        return [
            'surface' => 'decimal:2',
            'prix' => 'decimal:2',
            'statut' => StatutAppartement::class,
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function vente(): HasOne
    {
        return $this->hasOne(Vente::class);
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('statut', StatutAppartement::Disponible);
    }

    public function libelle(): string
    {
        return "{$this->programme?->nom} · {$this->reference} ({$this->typologie})";
    }
}
