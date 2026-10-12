<?php

namespace App\Policies;

use App\Enums\TypeEncaissement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Un associé ne voit que ses propres apports ; seuls les administrateurs modifient ou suppriment un apport. */
class EncaissementPolicy extends PolitiqueParEcran
{
    protected string $ecran = 'encaissements';

    public function view(User $user, Model $model): bool
    {
        if ($user->estAssocie()) {
            return $model->type === TypeEncaissement::ApportAssocie && (int) $model->associe_id === (int) $user->getKey();
        }

        return parent::view($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->modifiable($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->modifiable($user, $model);
    }

    private function modifiable(User $user, Model $model): bool
    {
        return $model->type !== TypeEncaissement::ApportAssocie || $user->estAdministrateur();
    }
}
