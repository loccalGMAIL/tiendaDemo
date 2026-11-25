<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

/**
 * Los turnos los abren y los cierran las acciones de la Caja; el listado y la ficha son del
 * administrador (con arqueo ciego, el vendedor no tiene que ver la diferencia). Un turno no se
 * edita ni se borra.
 */
class CashSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CashSession $cashSession): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CashSession $cashSession): bool
    {
        return false;
    }

    public function delete(User $user, CashSession $cashSession): bool
    {
        return false;
    }

    /**
     * Cierra cualquiera del equipo; desde la ficha del turno, el administrador (por ejemplo, un
     * turno que quedó abierto).
     */
    public function close(User $user, CashSession $cashSession): bool
    {
        return $cashSession->isOpen() && ($user->isAdmin() || $user->isSeller());
    }
}
