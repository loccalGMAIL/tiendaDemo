<?php

namespace App\Listeners;

use App\Actions\Stock\RecordMovement;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Events\SaleCreated;
use App\Facades\Modules;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Stock: la venta descuenta la existencia de cada variante. Con el ajuste
 * "bloquear" rechaza la venta si no alcanza (el evento corre dentro de la transacción
 * de la venta, así que se revierte completa); con "avisar" deja el stock en negativo.
 */
class DeductStockForSale
{
    public function __construct(private RecordMovement $record) {}

    public function handle(SaleCreated $event): void
    {
        if (! Modules::enabled(Module::Stock)) {
            return;
        }

        $sale = $event->sale;
        $sale->loadMissing('items');

        // Varias líneas de la misma variante se suman.
        $required = $sale->items
            ->groupBy('product_variant_id')
            ->map(fn ($lines): int => (int) $lines->sum('quantity'))
            ->sortKeys();

        // Se bloquean siempre en el mismo orden (por id).
        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $required->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (Modules::setting(Module::Stock, 'on_no_stock', 'warn') === 'block') {
            $this->ensureEnough($required->all(), $variants->all());
        }

        foreach ($required as $variantId => $quantity) {
            $variant = $variants->get($variantId);

            if ($variant === null) {
                continue;
            }

            $this->record->handle($variant, StockMovementType::Sale, -$quantity, $sale, $event->actor);
        }
    }

    /**
     * @param  array<int|string, int>  $required
     * @param  array<int|string, ProductVariant>  $variants
     *
     * @throws ValidationException
     */
    private function ensureEnough(array $required, array $variants): void
    {
        $messages = [];

        foreach ($required as $variantId => $quantity) {
            $variant = $variants[$variantId] ?? null;

            if ($variant !== null && $variant->stock < $quantity) {
                $messages[] = "No hay stock suficiente de «{$variant->fullName()}» (disponible: {$variant->stock}, pedido: {$quantity}).";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['items' => $messages]);
        }
    }
}
