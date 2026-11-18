<?php

namespace App\Listeners;

use App\Actions\Stock\RecordMovement;
use App\Enums\StockMovementType;
use App\Events\PurchaseCancelled;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Stock: al anular una factura quita exactamente lo que esa factura sumó (sus
 * movimientos de compra). No mira si el módulo sigue encendido: una factura cargada con
 * Stock apagado no tiene movimientos y no resta nada; una cargada con Stock encendido
 * siempre se revierte. Si la existencia actual no alcanza, rechaza la anulación (el
 * evento corre dentro de la transacción, así que no queda nada a medias).
 */
class RemoveStockForCancelledPurchase
{
    public function __construct(private RecordMovement $record) {}

    /**
     * @throws ValidationException
     */
    public function handle(PurchaseCancelled $event): void
    {
        $purchase = $event->purchase;

        $added = StockMovement::query()
            ->where('reference_type', $purchase->getMorphClass())
            ->where('reference_id', $purchase->getKey())
            ->where('type', StockMovementType::Purchase->value)
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($movements): int => (int) $movements->sum('quantity'))
            ->sortKeys();

        if ($added->isEmpty()) {
            return;
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $added->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $messages = [];

        foreach ($added as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant !== null && $variant->stock - $quantity < 0) {
                $messages[] = "No se puede anular: «{$variant->fullName()}» quedaría con stock negativo (hay {$variant->stock} y la factura sumó {$quantity}).";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['purchase' => $messages]);
        }

        foreach ($added as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant !== null && $quantity !== 0) {
                $this->record->handle($variant, StockMovementType::Cancellation, -$quantity, $purchase, $event->actor, "Anulación de factura {$purchase->invoice_number}");
            }
        }
    }
}
