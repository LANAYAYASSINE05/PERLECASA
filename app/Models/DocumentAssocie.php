<?php

namespace App\Models;

use App\Enums\CategorieDocument;
use App\Services\Alertes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Document partagé avec tous les associés (statuts, PV d'AG, bilans), déposé par l'administrateur. */
class DocumentAssocie extends Model
{
    public const DISQUE = 'local';

    public const DOSSIER = 'documents-associes';

    protected $table = 'documents_associes';

    protected $fillable = ['titre', 'categorie', 'date_document', 'fichier', 'nom_original'];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieDocument::class,
            'date_document' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (DocumentAssocie $d) => $d->cree_par ??= auth()->id());
        static::created(fn (DocumentAssocie $d) => Alertes::documentAssocie($d));

        static::updated(function (DocumentAssocie $d): void {
            if ($d->wasChanged('fichier') && $d->getOriginal('fichier')) {
                Storage::disk(self::DISQUE)->delete($d->getOriginal('fichier'));
            }
        });

        static::deleted(fn (DocumentAssocie $d) => Storage::disk(self::DISQUE)->delete($d->fichier));
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function nomTelechargement(): string
    {
        $extension = pathinfo($this->fichier, PATHINFO_EXTENSION);

        return str($this->titre)->slug().'.'.$extension;
    }
}
