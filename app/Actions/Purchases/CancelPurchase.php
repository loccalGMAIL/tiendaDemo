<?php

namespace App\Actions\Purchases;

use App\Actions\Purchases\Concerns\GuardsPurchasing;
use App\Enums\ActivityEvent;
use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseCancelled;
use App\Models\Purchase;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula una factura de compra con motivo obligatorio. Su orden (si tiene) vuelve a
 * emitida y los módulos revierten sus efectos (Stock resta lo que sumó y rechaza la
 * anulación si dejaría existencias negativas). El costo de los productos no se toca.
 */
class CancelPurchase
{
    use GuardsPurchasing;

    public function __construct(private ActivityLogger $logger) {}

    /**
     * @throws ValidationException
     */
    public function handle(Purchase $purchase, User $actor, string $reason): Purchase
    {
        $this->guard($actor);
        $reason = $this->resolveReason($reason);

        return DB::transaction(function () use ($purchase, $actor, $reason): Purchase {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['purchase' => 'La factura ya está anulada.']);
            }

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $order = $locked->order()->lockForUpdate()->first();

            if ($order !== null && $order->status === PurchaseOrderStatus::Received) {
                $order->items()->update(['received_quantity' => 0]);
                $order->update(['status' => PurchaseOrderStatus::Issued]);
            }

            $this->logger->log(
                ActivityEvent::Cancelled,
                "Anulación de factura de compra {$locked->invoice_number}: {$reason}",
                $locked,
                ['reason' => $reason, 'total' => $locked->total],
                $actor,
            );

            // Dentro de la transacción: Stock puede rechazar y se revierte todo.
            PurchaseCancelled::dispatch($locked, $actor);

            return $locked;
        });
    }
}
