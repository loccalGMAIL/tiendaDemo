<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * Los proveedores son solo del administrador (el vendedor no ve costos de compra).
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }

    /**
     * Un proveedor con órdenes o facturas no se borra: se desactiva.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin() && ! $supplier->hasHistory();
    }
}
