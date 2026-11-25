<?php

namespace App\Listeners;

use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Events\SaleCancelled;
use App\Facades\Modules;
use App\Models\CashSession;
use App\Models\Payment;
use App\Services\Money;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Control de caja: anular una venta cobrada en efectivo en una caja saca ese efectivo
 * de la caja abierta en ese momento. Primero la que eligió quien anula; si no eligió, el turno
 * de la venta si sigue abierto, o la única caja abierta. Con varias abiertas hay que elegir;
 * sin ninguna abierta la devolución queda fuera de caja. Una venta que no pasó por una caja
 * (online, o cargada sin turno) no toca ninguna.
 */
class RefundCashForCancelledSale
{
    /**
     * @throws ValidationException
     */
    public function handle(SaleCancelled $event): void
    {
        $sale = $event->sale;

        if (! Modules::enabled(Module::CashControl)) {
            return;
        }

        $cash = Money::sum(Payment::query()
            ->with('method')
            ->where('sale_id', $sale->getKey())
            ->whereNotNull('cash_session_id')
            ->get()
            ->filter(fn (Payment $payment): bool => $payment->method->is_cash)
            ->map(fn (Payment $payment): string => (string) $payment->amount)
            ->values()
            ->all());

        if (Money::compare($cash, '0') <= 0) {
            return;
        }

        $session = $this->target($event);

        if ($session === null) {
            return;
        }

        $movement = $session->movements()->make([
            'type' => CashMovementType::Refund,
            'amount' => Money::sub('0', $cash),
            'description' => "Devolución por anulación de {$sale->number()}",
            'user_id' => $event->actor?->getKey() ?? $sale->seller_id,
        ]);
        $movement->subject()->associate($sale);
        $movement->save();
    }

    /**
     * @throws ValidationException
     */
    private function target(SaleCancelled $event): ?CashSession
    {
        if ($event->refundCashSessionId !== null) {
            $chosen = CashSession::query()->open()->lockForUpdate()->find($event->refundCashSessionId);

            if ($chosen === null) {
                throw ValidationException::withMessages(['refund_cash_session_id' => 'La caja elegida para la devolución no está abierta.']);
            }

            return $chosen;
        }

        $own = $event->sale->cash_session_id === null
            ? null
            : CashSession::query()->open()->lockForUpdate()->find($event->sale->cash_session_id);

        if ($own !== null) {
            return $own;
        }

        $open = CashSession::query()->open()->lockForUpdate()->limit(2)->get();

        if ($open->count() > 1) {
            throw ValidationException::withMessages(['refund_cash_session_id' => 'Hay más de una caja abierta: elegí de cuál sale la devolución del efectivo.']);
        }

        return $open->first();
    }
}
