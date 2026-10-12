<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TypeDecaissement;
use App\Enums\TypeEncaissement;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Écrans ouverts à chaque rôle ; l'administrateur a tout. */
    private const ECRANS = [
        'comptable' => ['tableau', 'ventes', 'encaissements', 'decaissements', 'charges', 'fournisseurs', 'comptes', 'referentiel'],
        'caissier' => ['tableau', 'decaissements'],
        'associe' => ['tableau', 'investissements'],
    ];

    /** Écrans réservés à un rôle précis, même l'administrateur n'y a pas accès. */
    private const ECRANS_PROPRES = [
        'investissements' => Role::Associe,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'actif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'actif' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->actif;
    }

    public function estAdministrateur(): bool
    {
        return $this->role === Role::Administrateur;
    }

    public function estCaissier(): bool
    {
        return $this->role === Role::Caissier;
    }

    public function estAssocie(): bool
    {
        return $this->role === Role::Associe;
    }

    public function accede(string $ecran): bool
    {
        if (isset(self::ECRANS_PROPRES[$ecran])) {
            return $this->role === self::ECRANS_PROPRES[$ecran];
        }

        return $this->estAdministrateur()
            || in_array($ecran, self::ECRANS[$this->role->value] ?? [], true);
    }

    public function apports(): HasMany
    {
        return $this->hasMany(Encaissement::class, 'associe_id')->where('type', TypeEncaissement::ApportAssocie);
    }

    public function remboursements(): HasMany
    {
        return $this->hasMany(Decaissement::class, 'associe_id')->where('type', TypeDecaissement::RemboursementAssocie);
    }

    /** Associés avec apports_sum_montant et remboursements_sum_montant. */
    public function scopeAssociesAvecSoldes(Builder $query): Builder
    {
        return $query->where('role', Role::Associe)
            ->withSum('apports', 'montant')
            ->withSum('remboursements', 'montant');
    }

    /** Net investi d'un associé chargé par associesAvecSoldes(). */
    public function netCharge(): float
    {
        return (float) $this->apports_sum_montant - (float) $this->remboursements_sum_montant;
    }

    public function totalApporte(): float
    {
        return (float) $this->apports()->sum('montant');
    }

    public function totalRembourse(): float
    {
        return (float) $this->remboursements()->sum('montant');
    }

    public function netInvesti(): float
    {
        return $this->totalApporte() - $this->totalRembourse();
    }

    public function partDuCapital(): float
    {
        return self::part($this->netInvesti());
    }

    /** Part d'un net investi dans le net investi de tous les associés, en %. */
    public static function part(float $net): float
    {
        $total = (float) Encaissement::where('type', TypeEncaissement::ApportAssocie)->sum('montant')
            - (float) Decaissement::where('type', TypeDecaissement::RemboursementAssocie)->sum('montant');

        return $total > 0 ? round($net / $total * 100, 2) : 0.0;
    }
}
