<?php

namespace App\Policies;

use App\Enums\TypeDecaissement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Le caissier ne voit que les opérations de caisse, et ne modifie ni ne supprime rien une fois saisi. */
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
        return parent::update($user, $model) && ! $user->estCaissier();
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->estCaissier();
    }

    public function deleteAny(User $user): bool
    {
        return parent::deleteAny($user) && ! $user->estCaissier();
    }
}
