<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * El vendedor da de alta clientes (lo necesita para vender); solo el administrador los edita.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    /**
     * Un cliente con ventas no se borra: se desactiva.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Los listados precargan «sales_exists» (withExists) para no consultar una vez por fila.
        $hasSales = $customer->sales_exists ?? $customer->sales()->exists();

        return $user->isAdmin() && ! $hasSales;
    }
}
