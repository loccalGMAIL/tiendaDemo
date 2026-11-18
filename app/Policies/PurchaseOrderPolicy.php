<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Una orden se edita solo en borrador y no se borra: se anula con motivo.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin() && $order->isDraft();
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return false;
    }

    public function issue(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin() && $order->isDraft();
    }

    /**
     * Con una factura vigente primero hay que anular la factura.
     */
    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->isAdmin() && ! $order->isCancelled() && ! $order->purchase()->exists();
    }
}
