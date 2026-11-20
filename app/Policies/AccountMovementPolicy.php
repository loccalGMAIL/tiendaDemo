<?php

namespace App\Policies;

use App\Models\AccountMovement;
use App\Models\User;

/**
 * La cuenta corriente es un historial inmutable: nadie la crea, edita ni borra desde el panel.
 */
class AccountMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, AccountMovement $movement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AccountMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, AccountMovement $movement): bool
    {
        return false;
    }
}
