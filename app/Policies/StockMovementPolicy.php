<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * El historial de stock solo se consulta: nadie lo crea, edita ni borra desde el panel.
 */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }
}
