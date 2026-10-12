<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** L'agent d'encaissement ne voit que ses propres encaissements, et ne modifie ni ne supprime rien une fois saisi. */
class EncaissementPolicy extends PolitiqueParEcran
{
    protected string $ecran = 'encaissements';

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model)
            && (! $user->estEncaisseur() || (int) $model->cree_par === (int) $user->getKey());
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && ! $user->estEncaisseur();
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->estEncaisseur();
    }

    public function deleteAny(User $user): bool
    {
        return parent::deleteAny($user) && ! $user->estEncaisseur();
    }
}
