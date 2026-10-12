<?php

namespace App\Policies;

use App\Enums\TypeDecaissement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Le caissier ne voit que les opérations de caisse, et ne modifie ni ne supprime rien une fois saisi.
 * Seuls les administrateurs modifient ou suppriment un remboursement d'associé.
 */
class DecaissementPolicy extends PolitiqueParEcran
{
    protected string $ecran = 'decaissements';

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model)
            && (! $user->estCaissier() || $model->type === TypeDecaissement::OperationCaisse);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $user->estCaissier() && $this->modifiable($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->estCaissier() && $this->modifiable($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return parent::deleteAny($user) && ! $user->estCaissier();
    }

    private function modifiable(User $user, Model $model): bool
    {
        return $model->type !== TypeDecaissement::RemboursementAssocie || $user->estAdministrateur();
    }
}
