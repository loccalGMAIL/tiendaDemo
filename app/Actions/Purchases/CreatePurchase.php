<?php

namespace App\Actions\Purchases;

use App\Actions\Purchases\Concerns\GuardsPurchasing;
use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseCreated;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga una factura de compra, directa o desde una orden emitida. Copia los ítems con
 * su costo, deja el costo de cada producto como el de la factura (las ventas ya hechas
 * no cambian: copiaron su costo al vender) y pasa la orden a recibida. Los módulos
 * reaccionan al evento (Stock suma las existencias).
 */
class CreatePurchase
{
    use GuardsPurchasing;

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param  array{
     *     supplier_id?: int|string|null,
     *     purchase_order_id?: int|string|null,
     *     date?: string|null,
     *     invoice_number?: string|null,
     *     notes?: string|null,
     *     items?: array<int, array{variant_id?: int|string|null, quantity?: int|string|null, unit_cost?: string|int|float|null}>
     * }  $data
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): Purchase
    {
        $this->guard($actor);

        return DB::transaction(fn (): Purchase => $this->create($actor, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(User $actor, array $data): Purchase
    {
        $order = $this->resolveOrder($data['purchase_order_id'] ?? null);
        $supplier = $this->resolveSupplier($order === null ? ($data['supplier_id'] ?? null) : $order->supplier_id);
        $invoiceNumber = $this->resolveInvoiceNumber($data['invoice_number'] ?? null, $supplier->getKey());
        $lines = $this->resolveLines($data['items'] ?? [], $order);

        $purchase = new Purchase([
            'purchase_order_id' => $order?->getKey(),
            'supplier_id' => $supplier->getKey(),
            'date' => filled($data['date'] ?? null) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString(),
            'invoice_number' => $invoiceNumber,
            'total' => Money::sum(array_column($lines, 'subtotal')),
            'notes' => $data['notes'] ?? null,
            'user_id' => $actor->getKey(),
        ]);
        $purchase->save();

        foreach ($lines as $line) {
            $purchase->items()->create([
                'product_id' => $line['variant']->product_id,
                'product_variant_id' => $line['variant']->getKey(),
                'ordered_quantity' => $line['ordered_quantity'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'subtotal' => $line['subtotal'],
            ]);

            // El costo de la factura queda como costo del producto (la última gana).
            $line['variant']->product->update(['cost' => $line['unit_cost']]);
        }

        if ($order !== null) {
            $order->load('items');

            foreach ($order->items as $item) {
                $item->update(['received_quantity' => (int) collect($lines)
                    ->where('variant_id', $item->product_variant_id)
                    ->sum('quantity')]);
            }

            $order->update(['status' => PurchaseOrderStatus::Received]);
        }

        $this->logger->log(
            ActivityEvent::Created,
            "Alta de factura de compra {$invoiceNumber} ({$supplier->name})",
            $purchase,
            ['supplier' => $supplier->name, 'total' => $purchase->total, 'order' => $order?->number()],
            $actor,
        );

        // Dentro de la transacción: si un módulo rechaza, se revierte la factura completa.
        PurchaseCreated::dispatch($purchase, $actor);

        return $purchase;
    }

    /**
     * @throws ValidationException
     */
    private function resolveOrder(mixed $id): ?PurchaseOrder
    {
        if (blank($id)) {
            return null;
        }

        $order = PurchaseOrder::query()->lockForUpdate()->find($id);

        if ($order === null) {
            throw ValidationException::withMessages(['purchase_order_id' => 'La orden de compra no existe.']);
        }

        if ($order->status !== PurchaseOrderStatus::Issued) {
            throw ValidationException::withMessages(['purchase_order_id' => 'Solo se puede cargar la factura de una orden emitida.']);
        }

        return $order;
    }

    /**
     * El número no se repite para un mismo proveedor entre las facturas vigentes.
     *
     * @throws ValidationException
     */
    private function resolveInvoiceNumber(mixed $number, int $supplierId): string
    {
        $number = trim((string) $number);

        if ($number === '') {
            throw ValidationException::withMessages(['invoice_number' => 'El número de factura es obligatorio.']);
        }

        $exists = Purchase::query()
            ->notCancelled()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $number)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['invoice_number' => "Ya hay una factura vigente {$number} de este proveedor."]);
        }

        return $number;
    }

    /**
     * Líneas de la factura. Con orden: solo productos de la orden, sin pasarse de lo pedido
     * (una línea en cero es lo que no llegó). Directa: cantidades de 1 en adelante. En los
     * dos casos tiene que haber llegado algo.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return list<array{variant: ProductVariant, variant_id: int, ordered_quantity: int|null, quantity: int, unit_cost: string, subtotal: string}>
     *
     * @throws ValidationException
     */
    private function resolveLines(array $items, ?PurchaseOrder $order): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'La factura necesita al menos un producto.']);
        }

        $variants = $this->loadVariants($items);
        $ordered = $order?->items()->pluck('quantity', 'product_variant_id');
        $arrived = [];
        $lines = [];

        foreach ($items as $item) {
            $variantId = (int) $item['variant_id'];
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            $minimum = $order === null ? 1 : 0;

            if ($quantity === false || $quantity < $minimum) {
                throw ValidationException::withMessages(['items' => $order === null
                    ? 'Cada cantidad tiene que ser un entero de 1 en adelante.'
                    : 'Cada cantidad tiene que ser un entero de 0 en adelante.']);
            }

            if (! is_numeric($item['unit_cost'] ?? null) || Money::isNegative(Money::from($item['unit_cost']))) {
                throw ValidationException::withMessages(['items' => 'Cada costo tiene que ser un monto de 0 en adelante.']);
            }

            $variant = $variants->get($variantId);

            if ($ordered !== null && ! $ordered->has($variantId)) {
                throw ValidationException::withMessages(['items' => "«{$variant?->fullName()}» no estaba en la orden."]);
            }

            $arrived[$variantId] = ($arrived[$variantId] ?? 0) + $quantity;

            if ($ordered !== null && $arrived[$variantId] > $ordered->get($variantId)) {
                throw ValidationException::withMessages(['items' => "De «{$variant?->fullName()}» se pidieron {$ordered->get($variantId)} y no pueden llegar {$arrived[$variantId]}."]);
            }

            if ($quantity === 0) {
                continue;
            }

            $unitCost = Money::from($item['unit_cost']);

            $lines[] = [
                'variant' => $variant,
                'variant_id' => $variantId,
                'ordered_quantity' => $ordered?->get($variantId),
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => Money::mul($unitCost, $quantity),
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Tiene que haber llegado al menos un producto.']);
        }

        return $lines;
    }
}
