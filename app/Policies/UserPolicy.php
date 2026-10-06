<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Un administrateur ne peut pas supprimer son propre compte. */
class UserPolicy extends PolitiqueParEcran
{
    protected string $ecran = 'utilisateurs';

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
