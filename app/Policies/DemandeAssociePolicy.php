<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** L'administrateur traite toutes les demandes ; l'associé ne voit et ne crée que les siennes. */
class DemandeAssociePolicy extends PolitiqueParEcran
{
    protected string $ecran = 'associes';

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model)
            || ($user->accede('investissements') && (int) $model->associe_id === (int) $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->accede('investissements');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
