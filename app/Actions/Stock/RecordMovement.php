<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra un movimiento de stock y actualiza la existencia de la variante.
 * Hay que llamarla dentro de una transacción y con la variante ya bloqueada
 * (lockForUpdate); es la única vía por la que cambia `stock`.
 */
class RecordMovement
{
    public function handle(
        ProductVariant $variant,
        StockMovementType $type,
        int $quantity,
        ?Model $reference = null,
        ?User $user = null,
        ?string $reason = null,
    ): StockMovement {
        $balance = $variant->stock + $quantity;

        $variant->forceFill(['stock' => $balance])->save();

        $movement = new StockMovement([
            'product_variant_id' => $variant->getKey(),
            'type' => $type,
            'quantity' => $quantity,
            'balance' => $balance,
            'user_id' => $user?->getKey(),
            'reason' => $reason,
        ]);

        if ($reference !== null) {
            $movement->reference()->associate($reference);
        }

        $movement->save();

        return $movement;
    }
}
