<?php

namespace App\Listeners;

use App\Actions\Stock\RecordMovement;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Events\PurchaseCreated;
use App\Facades\Modules;
use App\Models\ProductVariant;

/**
 * Módulo Stock: la factura de compra suma a la existencia de cada variante. Corre
 * dentro de la transacción de la factura.
 */
class AddStockForPurchase
{
    public function __construct(private RecordMovement $record) {}

    public function handle(PurchaseCreated $event): void
    {
        if (! Modules::enabled(Module::Stock)) {
            return;
        }

        $purchase = $event->purchase;
        $purchase->loadMissing('items');

        // Varias líneas de la misma variante se suman.
        $received = $purchase->items
            ->groupBy('product_variant_id')
            ->map(fn ($lines): int => (int) $lines->sum('quantity'))
            ->sortKeys();

        // Se bloquean siempre en el mismo orden (por id).
        $variants = ProductVariant::query()
            ->whereIn('id', $received->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($received as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant !== null && $quantity > 0) {
                $this->record->handle($variant, StockMovementType::Purchase, $quantity, $purchase, $event->actor, "Factura {$purchase->invoice_number}");
            }
        }
    }
}
