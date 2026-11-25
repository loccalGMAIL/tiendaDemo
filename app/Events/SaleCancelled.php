<?php

namespace App\Events;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara dentro de la transacción de la anulación, para que los módulos
 * reviertan sus efectos junto con la venta. `refundCashSessionId` es el turno de caja del
 * que sale el efectivo a devolver, si quien anula lo eligió (módulo Control de caja).
 */
class SaleCancelled
{
    use Dispatchable;

    public function __construct(
        public Sale $sale,
        public ?User $actor = null,
        public ?int $refundCashSessionId = null,
    ) {}
}
