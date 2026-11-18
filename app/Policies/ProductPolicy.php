<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    /**
     * Un producto vendido no se borra: se desactiva.
     */
    public function delete(User $user, Product $product): bool
    {
        // Los listados precargan «sale_items_exists» (withExists) para no consultar una vez por fila.
        $wasSold = $product->sale_items_exists ?? $product->saleItems()->exists();

        return $user->isAdmin() && ! $wasSold;
    }
}
