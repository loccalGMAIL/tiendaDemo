<?php

namespace App\Listeners;

use App\Actions\Accounts\RecordAccountMovement;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Events\SaleCreated;
use App\Facades\Modules;
use App\Models\Installment;
use App\Models\Payment;
use App\Services\Money;

/**
 * Módulo Cuenta corriente: una venta con saldo arma su vencimiento y asienta en la cuenta
 * del cliente el débito por el total y el crédito por cada cobro hecho en el acto. Una
 * venta de contado no genera nada. Corre dentro de la transacción de la venta.
 */
class RegisterAccountForSale
{
    public function __construct(private RecordAccountMovement $record) {}

    public function handle(SaleCreated $event): void
    {
        $sale = $event->sale;

        if (! Modules::enabled(Module::CurrentAccount) || Money::compare((string) $sale->financed_amount, '0') <= 0 || $sale->customer === null) {
            return;
        }

        $sale->loadMissing('payments');

        Installment::query()->create([
            'sale_id' => $sale->getKey(),
            'customer_id' => $sale->customer_id,
            'number' => 1,
            'due_date' => $sale->first_due_date,
            'amount' => $sale->financed_amount,
            'paid_amount' => '0.00',
            // Parte del costo que corresponde a lo que quedó a cuenta.
            'cost_portion' => Money::round(bcdiv(bcmul((string) $sale->total_cost, (string) $sale->financed_amount, 8), (string) $sale->subtotal, 8)),
            'status' => InstallmentStatus::Pending,
        ]);

        $date = $sale->date->toDateString();

        $this->record->handle($sale->customer, AccountMovementType::Sale, (string) $sale->total, '0.00', "Venta {$sale->number()}", $sale, $event->actor, $date);

        foreach ($sale->payments as $payment) {
            /** @var Payment $payment */
            $this->record->handle($sale->customer, AccountMovementType::Payment, '0.00', (string) $payment->amount, "Cobro en el acto de {$sale->number()} ({$payment->number()})", $payment, $event->actor, $date);
        }
    }
}
