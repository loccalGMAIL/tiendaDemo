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
 * Anula una orden con motivo obligatorio. Si ya tiene una factura vigente, primero
 * hay que anular la factura (así cada reversión queda registrada).
 */
class CancelPurchaseOrder
{
    use GuardsPurchasing;

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @throws ValidationException
     */
    public function handle(PurchaseOrder $order, User $actor, string $reason): PurchaseOrder
    {
        $this->guard($actor);
        $reason = $this->resolveReason($reason);

        return DB::transaction(function () use ($order, $actor, $reason): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['order' => 'La orden ya está anulada.']);
            }

            if ($locked->purchase()->exists()) {
                throw ValidationException::withMessages(['order' => 'La orden tiene una factura cargada: anulá primero la factura.']);
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->logger->log(
                ActivityEvent::Cancelled,
                "Anulación de orden de compra {$locked->number()}: {$reason}",
                $locked,
                ['reason' => $reason],
                $actor,
            );

            return $locked;
        });
    }
}
