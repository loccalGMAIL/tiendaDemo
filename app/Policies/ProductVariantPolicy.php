<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin();
    }

    /**
     * Una variante con ventas o movimientos de stock no se borra: se desactiva.
     */
    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->isAdmin() && ! $variant->isImplicit() && ! $variant->hasHistory();
    }
}
