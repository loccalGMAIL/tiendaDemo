<?php

namespace App\Policies;

use App\Models\Installment;
use App\Models\User;

/**
 * Los vencimientos solo se consultan: se generan con la venta y cambian con los cobros.
 */
class InstallmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSeller();
    }

    public function view(User $user, Installment $installment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Installment $installment): bool
    {
        return false;
    }

    public function delete(User $user, Installment $installment): bool
    {
        return false;
    }
}
