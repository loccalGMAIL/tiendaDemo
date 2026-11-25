<?php

namespace App\Listeners;

use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Enums\SaleChannel;
use App\Events\SaleCreated;
use App\Facades\Modules;
use App\Models\CashSession;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Control de caja: una venta cobrada en una caja deja sus cobros en el turno y suma al
 * cajón lo cobrado con medios de efectivo. Solo las ventas de mostrador pasan por una caja, y
 * el turno tiene que estar abierto; si no, la venta completa se revierte (el evento corre
 * dentro de su transacción). Una venta sin turno no toca ninguna caja.
 */
class RegisterSaleInCashSession
{
    /**
     * @throws ValidationException
     */
    public function handle(SaleCreated $event): void
    {
        $sale = $event->sale;

        if ($sale->cash_session_id === null) {
            return;
        }

        if (! Modules::enabled(Module::CashControl)) {
            throw ValidationException::withMessages(['cash_session_id' => 'El módulo Control de caja está apagado.']);
        }

        if ($sale->channel !== SaleChannel::Counter) {
            throw ValidationException::withMessages(['cash_session_id' => 'Una venta online no pasa por la caja.']);
        }

        $session = CashSession::query()->with('register')->lockForUpdate()->find($sale->cash_session_id);

        if ($session === null || ! $session->isOpen()) {
            throw ValidationException::withMessages(['cash_session_id' => 'La caja está cerrada: abrila de nuevo para cobrar.']);
        }

        $sale->loadMissing('payments.method');

        foreach ($sale->payments as $payment) {
            $payment->forceFill(['cash_session_id' => $session->getKey()])->save();

            if (! $payment->method->is_cash) {
                continue;
            }

            $movement = $session->movements()->make([
                'type' => CashMovementType::Sale,
                'amount' => (string) $payment->amount,
                'description' => "Venta {$sale->number()} ({$payment->method->name})",
                'user_id' => $event->actor?->getKey() ?? $sale->seller_id,
            ]);
            $movement->subject()->associate($sale);
            $movement->save();
        }
    }
}
