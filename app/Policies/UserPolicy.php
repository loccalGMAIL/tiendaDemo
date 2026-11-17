<?php

namespace App\Policies;

use App\Models\User;

/**
 * El administrador del cliente gestiona a sus usuarios sin ver nunca al SuperAdmin.
 * El SuperAdmin (panel de la plataforma) gestiona a todos, incluidos otros SuperAdmin.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && ! $model->isSuperAdmin());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && ! $model->isSuperAdmin());
    }

    public function delete(User $user, User $model): bool
    {
        return ($user->isSuperAdmin() || ($user->isAdmin() && ! $model->isSuperAdmin()))
            && ! $user->is($model)
            && ! $model->hasOperations();
    }
}
