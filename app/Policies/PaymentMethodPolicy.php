<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin();
    }

    /**
     * Un medio de pago usado en cobros no se borra: se desactiva.
     */
    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin() && ! $method->payments()->exists();
    }
}
