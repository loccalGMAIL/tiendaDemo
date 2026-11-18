<?php

namespace App\Actions\Purchases;

use App\Actions\Purchases\Concerns\GuardsPurchasing;
use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Emite una orden en borrador: ya no se edita y queda esperando su factura.
 */
class IssuePurchaseOrder
{
    use GuardsPurchasing;

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        $this->guard($actor);

        return DB::transaction(function () use ($order, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages(['order' => 'Solo se puede emitir una orden en borrador.']);
            }

            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages(['order' => 'La orden no tiene productos.']);
            }

            $locked->update(['status' => PurchaseOrderStatus::Issued, 'issued_at' => now()]);

            $this->logger->log(
                ActivityEvent::Updated,
                "Emisión de orden de compra {$locked->number()}",
                $locked,
                ['status' => PurchaseOrderStatus::Issued->value],
                $actor,
            );

            return $locked;
        });
    }
}
