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
use App\Models\Sale;
use App\Services\InstallmentSchedule;
use App\Services\Money;

/**
 * Módulo Cuenta corriente: una venta con saldo arma sus vencimientos (uno solo, o las cuotas de
 * su plan con Cuotas propias) y asienta en la cuenta del cliente el débito por el total, el
 * débito del recargo de financiación y el crédito por cada cobro hecho en el acto. Una venta de
 * contado no genera nada. Corre dentro de la transacción de la venta.
 */
class RegisterAccountForSale
{
    public function __construct(
        private RecordAccountMovement $record,
        private InstallmentSchedule $schedule,
    ) {}

    public function handle(SaleCreated $event): void
    {
        $sale = $event->sale;

        if (! Modules::enabled(Module::CurrentAccount) || Money::compare((string) $sale->financed_amount, '0') <= 0 || $sale->customer === null) {
            return;
        }

        $sale->loadMissing('payments');

        $this->createInstallments($sale);

        $date = $sale->date->toDateString();

        $this->record->handle($sale->customer, AccountMovementType::Sale, (string) $sale->total, '0.00', "Venta {$sale->number()}", $sale, $event->actor, $date);

        foreach ($sale->payments as $payment) {
            /** @var Payment $payment */
            $this->record->handle($sale->customer, AccountMovementType::Payment, '0.00', (string) $payment->amount, "Cobro en el acto de {$sale->number()} ({$payment->number()})", $payment, $event->actor, $date);
        }

        // El recargo de financiación va aparte del total de la venta: así el saldo del libro
        // sigue siendo la suma de los vencimientos.
        if (Money::compare((string) $sale->financing_surcharge, '0') > 0) {
            $this->record->handle($sale->customer, AccountMovementType::Adjustment, (string) $sale->financing_surcharge, '0.00', "Recargo de financiación de {$sale->number()} ({$sale->installments_count} cuotas)", $sale, $event->actor, $date);
        }
    }

    /**
     * Una cuota por cada fila del cronograma (una sola si no hay plan). La parte del costo se
     * reparte sobre lo financiado sin recargo.
     */
    private function createInstallments(Sale $sale): void
    {
        $financed = (string) $sale->financed_amount;
        $base = Money::sub($financed, (string) $sale->financing_surcharge);
        $count = max(1, (int) $sale->installments_count);

        $rule = (string) Modules::setting(Module::Installments, 'due_rule', InstallmentSchedule::RULE_FIXED_DAY);
        $fixedDay = (int) Modules::setting(Module::Installments, 'fixed_day', 10);

        $rows = $this->schedule->build(
            $financed,
            $count,
            $sale->first_due_date ?? $sale->date,
            $this->schedule->anchorDay($sale->date, $rule, $fixedDay),
            $this->schedule->financedCost((string) $sale->total_cost, $base, (string) $sale->subtotal),
        );

        foreach ($rows as $row) {
            Installment::query()->create([
                'sale_id' => $sale->getKey(),
                'customer_id' => $sale->customer_id,
                'number' => $row['number'],
                'due_date' => $row['due_date'],
                'amount' => $row['amount'],
                'paid_amount' => '0.00',
                'cost_portion' => $row['cost_portion'],
                'status' => InstallmentStatus::Pending,
            ]);
        }
    }
}
