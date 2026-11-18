<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/**
 * Una factura de compra no se edita ni se borra: solo se anula.
 */
class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->isAdmin() && ! $purchase->isCancelled();
    }
}
