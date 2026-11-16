<?php

namespace App\Events;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara dentro de la transacción de la anulación, para que los módulos
 * reviertan sus efectos junto con la venta.
 */
class SaleCancelled
{
    use Dispatchable;

    public function __construct(public Sale $sale, public ?User $actor = null) {}
}
