<?php

namespace App\Events;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara dentro de la transacción de la factura de compra, cuando ya están creados
 * sus ítems. Stock lo escucha para sumar las existencias; si un listener lanza una
 * excepción, la factura completa se revierte.
 */
class PurchaseCreated
{
    use Dispatchable;

    public function __construct(public Purchase $purchase, public ?User $actor = null) {}
}
