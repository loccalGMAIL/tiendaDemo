<?php

namespace App\Actions\Sales;

use App\Enums\ActivityEvent;
use App\Enums\SaleStatus;
use App\Events\SaleCancelled;
use App\Models\Sale;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula una venta con motivo obligatorio. Una venta confirmada no se edita ni
 * se borra: queda en el historial con quién, cuándo y por qué. Anular revierte
 * todos sus efectos (cobros anulados; los módulos reaccionan al evento).
 */
class CancelSale
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Sale $sale, User $actor, string $reason): Sale
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede anular ventas.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo de la anulación es obligatorio.']);
        }

        $cancelled = DB::transaction(function () use ($sale, $actor, $reason): Sale {
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['sale' => 'La venta ya está anulada.']);
            }

            $now = now();

            $locked->forceFill([
                'status' => SaleStatus::Cancelled,
                'cancelled_at' => $now,
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            foreach ($locked->payments()->whereNull('cancelled_at')->orderBy('id')->lockForUpdate()->get() as $payment) {
                $payment->forceFill([
                    'cancelled_at' => $now,
                    'cancelled_by' => $actor->getKey(),
                    'cancellation_reason' => $reason,
                ])->save();
            }

            $this->logger->log(
                ActivityEvent::Cancelled,
                "Anulación de venta {$locked->number()}: {$reason}",
                $locked,
                ['reason' => $reason, 'total' => $locked->total],
                $actor,
            );

            return $locked;
        });

        SaleCancelled::dispatch($cancelled);

        return $cancelled;
    }
}
