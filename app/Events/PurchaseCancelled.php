<?php

namespace App\Events;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara dentro de la transacción de la anulación, para que los módulos revierten
 * sus efectos junto con la factura (Stock puede rechazarla si dejaría existencias negativas).
 */
class PurchaseCancelled
{
    use Dispatchable;

    public function __construct(public Purchase $purchase, public ?User $actor = null) {}
}
