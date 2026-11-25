<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;

/**
 * Las cajas las administra el administrador (módulo Control de caja). Abrir y cerrar turnos
 * no pasa por esta policy: lo hace el equipo desde la Caja.
 */
class CashRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CashRegister $cashRegister): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CashRegister $cashRegister): bool
    {
        return $user->isAdmin();
    }

    /**
     * Una caja con turnos no se borra: se desactiva. Usa `sessions_exists` si el listado lo trae.
     */
    public function delete(User $user, CashRegister $cashRegister): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        $hasSessions = $cashRegister->getAttribute('sessions_exists') ?? $cashRegister->sessions()->exists();

        return ! $hasSessions;
    }
}
