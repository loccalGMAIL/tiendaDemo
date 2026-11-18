<?php

namespace App\Policies;

use App\Models\FinancingPlan;
use App\Models\User;

class FinancingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, FinancingPlan $plan): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, FinancingPlan $plan): bool
    {
        return $user->isAdmin();
    }

    /**
     * Un plan que ya se usó en ventas no se borra: se desactiva.
     */
    public function delete(User $user, FinancingPlan $plan): bool
    {
        return $user->isAdmin() && ! $plan->sales()->exists();
    }
}
