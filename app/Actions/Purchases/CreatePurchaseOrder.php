<?php

namespace App\Actions\Purchases;

use App\Actions\Purchases\Concerns\GuardsPurchasing;
use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crea una orden de compra en borrador: proveedor, fecha y lo que se pide.
 */
class CreatePurchaseOrder
{
    use GuardsPurchasing;

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param  array{
     *     supplier_id?: int|string|null,
     *     date?: string|null,
     *     notes?: string|null,
     *     items?: array<int, array{variant_id?: int|string|null, quantity?: int|string|null}>
     * }  $data
     */
    public function handle(User $actor, array $data): PurchaseOrder
    {
        $this->guard($actor);

        return DB::transaction(function () use ($actor, $data): PurchaseOrder {
            $supplier = $this->resolveSupplier($data['supplier_id'] ?? null);
            $lines = $this->orderLines($data['items'] ?? []);

            $order = new PurchaseOrder([
                'supplier_id' => $supplier->getKey(),
                'date' => filled($data['date'] ?? null) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString(),
                'status' => PurchaseOrderStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'user_id' => $actor->getKey(),
            ]);
            $order->save();

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['variant']->product_id,
                    'product_variant_id' => $line['variant']->getKey(),
                    'quantity' => $line['quantity'],
                ]);
            }

            $this->logger->log(
                ActivityEvent::Created,
                "Alta de orden de compra {$order->number()} ({$supplier->name})",
                $order,
                ['supplier' => $supplier->name, 'items' => count($lines)],
                $actor,
            );

            return $order;
        });
    }
}
