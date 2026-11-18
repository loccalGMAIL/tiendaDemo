<?php

namespace App\Actions\Purchases;

use App\Actions\Purchases\Concerns\GuardsPurchasing;
use App\Enums\ActivityEvent;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edita una orden mientras esté en borrador: proveedor, fecha, notas y ítems.
 */
class UpdatePurchaseOrder
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
     *
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, User $actor, array $data): PurchaseOrder
    {
        $this->guard($actor);

        return DB::transaction(function () use ($order, $actor, $data): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['order' => 'Solo se puede editar una orden en borrador.']);
            }

            $supplier = $this->resolveSupplier($data['supplier_id'] ?? null);
            $lines = $this->orderLines($data['items'] ?? []);

            $locked->update([
                'supplier_id' => $supplier->getKey(),
                'date' => filled($data['date'] ?? null) ? Carbon::parse($data['date'])->toDateString() : $locked->date->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->items()->delete();

            foreach ($lines as $line) {
                $locked->items()->create([
                    'product_id' => $line['variant']->product_id,
                    'product_variant_id' => $line['variant']->getKey(),
                    'quantity' => $line['quantity'],
                ]);
            }

            $this->logger->log(
                ActivityEvent::Updated,
                "Cambio en orden de compra {$locked->number()} ({$supplier->name})",
                $locked,
                ['supplier' => $supplier->name, 'items' => count($lines)],
                $actor,
            );

            return $locked;
        });
    }
}
