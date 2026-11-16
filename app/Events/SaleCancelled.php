<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara al anular una venta, para que los módulos reviertan sus efectos.
 */
class SaleCancelled
{
    use Dispatchable;

    public function __construct(public Sale $sale) {}
}
