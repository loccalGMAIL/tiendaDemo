<?php

namespace App\Actions\Accounts;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Anula un cobro a cuenta con motivo obligatorio (solo administrador): devuelve a cada
 * vencimiento lo que se le había imputado, reabre las ventas que habían quedado pagadas y
 * deja en la cuenta del cliente un débito de ajuste. El cobro hecho en el acto de una venta
 * no se anula solo: se anula con la venta.
 */
class CancelPayment
{
    public function __construct(
        private RecordAccountMovement $record,
        private ActivityLogger $logger,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $actor, string $reason): Payment
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Solo un administrador puede anular cobros.');
        }

        if (! Modules::enabled(Module::CurrentAccount)) {
            throw ValidationException::withMessages(['account' => 'El módulo Cuenta corriente está apagado.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo de la anulación es obligatorio.']);
        }

        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['payment' => 'El cobro ya está anulado.']);
            }

            if (! $locked->isOnAccount()) {
                throw ValidationException::withMessages(['payment' => 'El cobro hecho en el acto de una venta se anula con la venta.']);
            }

            $locked->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->reopen($locked);

            $this->record->handle(
                $locked->customer,
                AccountMovementType::Adjustment,
                (string) $locked->base_amount,
                '0.00',
                "Anulación de cobro {$locked->number()}: {$reason}",
                $locked,
                $actor,
            );

            $this->logger->log(
                ActivityEvent::Cancelled,
                "Anulación de cobro {$locked->number()}: {$reason}",
                $locked,
                ['reason' => $reason, 'amount' => $locked->amount],
                $actor,
            );

            return $locked;
        });
    }

    /**
     * Devuelve a cada vencimiento lo imputado y reabre las ventas que quedaron pagadas.
     */
    private function reopen(Payment $payment): void
    {
        // Lo imputado a cada vencimiento (pivot installment_payment).
        $imputed = DB::table('installment_payment')->where('payment_id', $payment->getKey())->pluck('amount', 'installment_id');

        $installments = Installment::query()
            ->whereIn('id', $imputed->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $installment) {
            $paid = Money::sub((string) $installment->paid_amount, Money::from($imputed->get($installment->getKey())));

            $installment->update([
                'paid_amount' => $paid,
                'status' => Money::compare($paid, '0') <= 0 ? InstallmentStatus::Pending : InstallmentStatus::Partial,
            ]);
        }

        foreach ($installments->pluck('sale_id')->unique() as $saleId) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);

            if ($sale->status === SaleStatus::Paid) {
                $sale->update(['status' => SaleStatus::Active]);
            }
        }
    }
}
