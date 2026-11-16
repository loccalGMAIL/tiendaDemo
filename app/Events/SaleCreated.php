<?php

namespace App\Events;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara dentro de la transacción de la venta, cuando ya están creados sus
 * ítems y cobros. Los módulos (Stock, etc.) lo escuchan; el núcleo no los llama.
 * Si un listener lanza una excepción (p. ej. Stock en modo bloquear), la venta
 * completa se revierte.
 */
class SaleCreated
{
    use Dispatchable;

    public function __construct(public Sale $sale, public ?User $actor = null) {}
}
