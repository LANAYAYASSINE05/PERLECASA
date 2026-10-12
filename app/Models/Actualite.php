<?php

namespace App\Models;

use App\Services\Alertes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Annonce publiée par l'administrateur pour les associés. */
class Actualite extends Model
{
    protected $fillable = ['titre', 'contenu', 'epingle'];

    protected function casts(): array
    {
        return ['epingle' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Actualite $a) => $a->cree_par ??= auth()->id());
        static::created(fn (Actualite $a) => Alertes::actualite($a));
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
