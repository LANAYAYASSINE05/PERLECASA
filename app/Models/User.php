<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    public function accede(string $ecran): bool
    {
        return $this->estAdministrateur()
            || in_array($ecran, self::ECRANS[$this->role->value] ?? [], true);
    }
}
