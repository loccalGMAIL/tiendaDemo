<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Cobros a cuenta (módulo Cuenta corriente). El vendedor los registra y ve los suyos y los de
 * sus ventas (salvo SELLER_SEES_ALL_SALES); solo el administrador los anula. Un cobro no se
 * edita ni se borra.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSeller()
            && (config('platform.sales.seller_sees_all')
                || $payment->collected_by === $user->getKey()
                || $payment->sale?->seller_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Solo el administrador, una sola vez y solo los cobros a cuenta (el anticipo se anula con la venta).
     */
    public function cancel(User $user, Payment $payment): bool
    {
        return $user->isAdmin() && ! $payment->isCancelled() && $payment->isOnAccount();
    }
}
