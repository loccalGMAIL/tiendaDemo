<?php

namespace App\Listeners;

use App\Actions\Stock\RecordMovement;
use App\Enums\StockMovementType;
use App\Events\SaleCancelled;
use App\Models\ProductVariant;
use App\Models\StockMovement;

/**
 * Módulo Stock: al anular una venta devuelve exactamente lo que esa venta descontó
 * (sus movimientos de venta). No mira si el módulo sigue encendido: una venta hecha
 * con Stock apagado no tiene movimientos y no suma nada; una hecha con Stock
 * encendido siempre se revierte.
 */
class RestoreStockForCancelledSale
{
    public function __construct(private RecordMovement $record) {}

    public function handle(SaleCancelled $event): void
    {
        $sale = $event->sale;

        $deducted = StockMovement::query()
            ->where('reference_type', $sale->getMorphClass())
            ->where('reference_id', $sale->getKey())
            ->where('type', StockMovementType::Sale->value)
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($movements): int => -(int) $movements->sum('quantity'))
            ->sortKeys();

        if ($deducted->isEmpty()) {
            return;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $deducted->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($deducted as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant !== null && $quantity !== 0) {
                $this->record->handle($variant, StockMovementType::Cancellation, $quantity, $sale, $event->actor);
            }
        }
    }
}
