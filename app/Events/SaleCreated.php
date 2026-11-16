<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara al confirmar una venta. Los módulos (Stock, etc.) lo escuchan;
 * el núcleo no los llama directamente.
 */
class SaleCreated
{
    use Dispatchable;

    public function __construct(public Sale $sale) {}
}
