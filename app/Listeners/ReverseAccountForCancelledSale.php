<?php

namespace App\Listeners;

use App\Actions\Accounts\RecordAccountMovement;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Events\SaleCancelled;
use App\Models\Installment;
use App\Services\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Cuenta corriente: al anular una venta con saldo anula sus vencimientos y deja en
 * la cuenta del cliente un crédito de ajuste por el total y un débito de ajuste por lo que
 * se cobró en el acto (neto cero). No mira si el módulo sigue encendido: una venta con
 * vencimientos siempre se revierte. Si ya tiene cobros imputados la rechaza (hay que anular
 * antes esos cobros, así cada devolución queda registrada); el evento corre dentro de la
 * transacción, así que no queda nada a medias.
 */
class ReverseAccountForCancelledSale
{
    public function __construct(private RecordAccountMovement $record) {}

    /**
     * @throws ValidationException
     */
    public function handle(SaleCancelled $event): void
    {
        $sale = $event->sale;

        $installments = Installment::query()
            ->where('sale_id', $sale->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($installments->isEmpty() || $sale->customer === null) {
            return;
        }

        $imputed = DB::table('installment_payment')
            ->join('payments', 'payments.id', '=', 'installment_payment.payment_id')
            ->whereIn('installment_payment.installment_id', $installments->modelKeys())
            ->whereNull('payments.cancelled_at')
            ->exists();

        if ($imputed) {
            throw ValidationException::withMessages(['sale' => 'La venta tiene cobros imputados: anulá primero esos cobros.']);
        }

        foreach ($installments as $installment) {
            $installment->update(['status' => InstallmentStatus::Cancelled]);
        }

        $this->record->handle($sale->customer, AccountMovementType::Adjustment, '0.00', (string) $sale->total, "Anulación de venta {$sale->number()}", $sale, $event->actor);

        if (Money::compare((string) $sale->down_payment, '0') > 0) {
            $this->record->handle($sale->customer, AccountMovementType::Adjustment, (string) $sale->down_payment, '0.00', "Anulación de lo cobrado en el acto de {$sale->number()}", $sale, $event->actor);
        }
    }
}
