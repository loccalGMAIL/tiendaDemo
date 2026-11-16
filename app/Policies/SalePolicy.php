<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/**
 * Una venta confirmada no se edita ni se borra: solo se anula.
 */
class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    /**
     * El vendedor ve solo sus ventas (salvo SELLER_SEES_ALL_SALES).
     */
    public function view(User $user, Sale $sale): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSeller()
            && (config('platform.sales.seller_sees_all') || $sale->seller_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function update(User $user, Sale $sale): bool
    {
        return false;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return false;
    }

    /**
     * Solo el administrador anula, y una sola vez.
     */
    public function cancel(User $user, Sale $sale): bool
    {
        return $user->isAdmin() && ! $sale->isCancelled();
    }
}
