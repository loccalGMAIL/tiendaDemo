<?php

namespace App\Actions\Accounts;

use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobra a un cliente a cuenta (módulo Cuenta corriente): el cobro se imputa a sus
 * vencimientos del más viejo al más nuevo (o solo a los elegidos), admite pagos parciales y
 * adelantados y no puede superar la deuda. Copia cuotas y porcentaje del medio de pago
 * (el recargo o descuento no baja la deuda: la baja la base). Deja el crédito en la cuenta
 * del cliente y pasa a pagadas las ventas que se saldan.
 */
class CollectPayment
{
    public function __construct(
        private RecordAccountMovement $record,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array{
     *     customer_id?: int|string|null,
     *     payment_method_id?: int|string|null,
     *     base_amount?: string|int|float|null,
     *     date?: string|null,
     *     notes?: string|null,
     *     installment_ids?: array<int, int|string>|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): Payment
    {
        if (! $actor->isAdmin() && ! $actor->isSeller()) {
            throw new AuthorizationException('No podés registrar cobros.');
        }

        if (! Modules::enabled(Module::CurrentAccount)) {
            throw ValidationException::withMessages(['account' => 'El módulo Cuenta corriente está apagado.']);
        }

        return DB::transaction(fn (): Payment => $this->collect($actor, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function collect(User $actor, array $data): Payment
    {
        $customer = Customer::query()->lockForUpdate()->find($data['customer_id'] ?? 0);

        if ($customer === null) {
            throw ValidationException::withMessages(['customer_id' => 'Elegí un cliente.']);
        }

        $method = PaymentMethod::query()->find($data['payment_method_id'] ?? 0);

        if ($method === null || ! $method->active) {
            throw ValidationException::withMessages(['payment_method_id' => 'El medio de pago elegido no es válido.']);
        }

        $base = $this->resolveBase($data['base_amount'] ?? null);
        $installments = $this->resolveInstallments($customer, $data['installment_ids'] ?? null);
        $this->ensureWithinDebt($base, $installments, filled($data['installment_ids'] ?? null));

        $date = filled($data['date'] ?? null) ? Carbon::parse($data['date'])->toDateString() : now()->toDateString();
        $adjustment = Money::percentOf($base, (string) $method->adjustment_percent);

        $payment = new Payment([
            'customer_id' => $customer->getKey(),
            'sale_id' => null,
            'date' => $date,
            'payment_method_id' => $method->getKey(),
            // Se copian del medio: cambiarlo después no afecta lo ya cobrado.
            'installments' => $method->installments,
            'adjustment_percent' => $method->adjustment_percent,
            'base_amount' => $base,
            'adjustment_amount' => $adjustment,
            'amount' => Money::add($base, $adjustment),
            'collected_by' => $actor->getKey(),
            'notes' => $data['notes'] ?? null,
        ]);
        $payment->save();

        $this->impute($payment, $base, $installments);

        $this->record->handle(
            $customer,
            AccountMovementType::Payment,
            '0.00',
            $base,
            "Cobro {$payment->number()}",
            $payment,
            $actor,
            $date,
        );

        $this->logger->log(
            ActivityEvent::Created,
            "Alta de cobro {$payment->number()} de {$customer->name}: {$payment->amount}",
            $payment,
            ['customer' => $customer->name, 'base' => $base, 'amount' => $payment->amount],
            $actor,
        );

        return $payment->load('imputedInstallments');
    }

    /**
     * @throws ValidationException
     */
    private function resolveBase(mixed $value): string
    {
        if (! is_numeric($value) || Money::compare(Money::from($value), '0') <= 0) {
            throw ValidationException::withMessages(['base_amount' => 'El monto a cobrar tiene que ser mayor que cero.']);
        }

        return Money::from($value);
    }

    /**
     * Vencimientos con deuda del cliente (todos, o solo los elegidos), del más viejo al más nuevo.
     *
     * @param  array<int, int|string>|null  $ids
     * @return Collection<int, Installment>
     *
     * @throws ValidationException
     */
    private function resolveInstallments(Customer $customer, ?array $ids): Collection
    {
        $installments = Installment::query()
            ->where('customer_id', $customer->getKey())
            ->open()
            ->oldestFirst()
            ->lockForUpdate()
            ->get();

        if (blank($ids)) {
            return $installments;
        }

        $chosen = $installments->whereIn('id', array_map('intval', $ids));

        if ($chosen->count() !== count(array_unique(array_map('intval', $ids)))) {
            throw ValidationException::withMessages(['installment_ids' => 'Hay vencimientos que no son de este cliente o ya no tienen deuda.']);
        }

        return $chosen->values();
    }

    /**
     * @param  Collection<int, Installment>  $installments
     *
     * @throws ValidationException
     */
    private function ensureWithinDebt(string $base, Collection $installments, bool $chosen): void
    {
        $debt = Money::sum($installments->map(fn (Installment $installment): string => $installment->balance())->all());

        if (Money::compare($base, $debt) > 0) {
            throw ValidationException::withMessages(['base_amount' => $chosen
                ? "El monto ({$base}) supera el saldo de los vencimientos elegidos ({$debt})."
                : "No se puede cobrar más que la deuda del cliente ({$debt}).",
            ]);
        }
    }

    /**
     * Imputa la base a los vencimientos en orden y pasa a pagadas las ventas que se saldan.
     *
     * @param  Collection<int, Installment>  $installments
     */
    private function impute(Payment $payment, string $base, Collection $installments): void
    {
        $remaining = $base;
        $saleIds = [];

        foreach ($installments as $installment) {
            if (Money::compare($remaining, '0') <= 0) {
                break;
            }

            $balance = $installment->balance();
            $take = Money::compare($remaining, $balance) < 0 ? $remaining : $balance;
            $paid = Money::add((string) $installment->paid_amount, $take);

            $installment->update([
                'paid_amount' => $paid,
                'status' => Money::compare($paid, (string) $installment->amount) >= 0 ? InstallmentStatus::Paid : InstallmentStatus::Partial,
            ]);

            $payment->imputedInstallments()->attach($installment->getKey(), ['amount' => $take]);

            $remaining = Money::sub($remaining, $take);
            $saleIds[] = $installment->sale_id;
        }

        foreach (array_unique($saleIds) as $saleId) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($saleId);

            $hasDebt = $sale->installments()->whereIn('status', InstallmentStatus::open())->exists();

            if (! $hasDebt && $sale->status === SaleStatus::Active) {
                $sale->update(['status' => SaleStatus::Paid]);
            }
        }
    }
}
