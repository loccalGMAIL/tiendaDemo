<?php

use App\Actions\Accounts\CollectPayment;
use App\Actions\Sales\CreateSale;
use App\Enums\AccountMovementType;
use App\Enums\ActivityEvent;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\AccountMovement;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::CurrentAccount);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    $this->none = PaymentMethod::factory()->create(['name' => 'Transferencia', 'adjustment_percent' => '0.00']);
    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00']);

    // A: 3.000 que vencen el 20/11 (más nuevo). B: 2.000 que vencieron el 10/11 (más viejo).
    $this->saleA = collectSale(3, '2026-11-20');
    $this->saleB = collectSale(2, '2026-11-10');
    $this->installmentA = $this->saleA->installments()->firstOrFail();
    $this->installmentB = $this->saleB->installments()->firstOrFail();
});

function collectSale(int $quantity, string $due, ?Customer $customer = null): Sale
{
    $test = test();

    return app(CreateSale::class)->handle($test->admin, [
        'customer_id' => ($customer ?? $test->customer)->id,
        'date' => '2026-11-01',
        'due_date' => $due,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => $quantity]],
    ]);
}

function receivePayment(string $base, array $extra = [], ?User $actor = null): Payment
{
    $test = test();

    return app(CollectPayment::class)->handle($actor ?? $test->admin, [
        'customer_id' => $test->customer->id,
        'payment_method_id' => $test->none->id,
        'base_amount' => $base,
        'date' => '2026-11-16',
        ...$extra,
    ]);
}

it('imputa el cobro al vencimiento más viejo primero y sigue con el siguiente', function () {
    $payment = receivePayment('2500.00');

    $this->installmentB->refresh();
    $this->installmentA->refresh();

    expect($this->installmentB->status)->toBe(InstallmentStatus::Paid)
        ->and($this->installmentB->paid_amount)->toBe('2000.00')
        ->and($this->installmentA->status)->toBe(InstallmentStatus::Partial)
        ->and($this->installmentA->paid_amount)->toBe('500.00')
        ->and($this->installmentA->balance())->toBe('2500.00')
        ->and($payment->imputedInstallments)->toHaveCount(2)
        ->and($payment->imputedInstallments->firstWhere('id', $this->installmentB->id)->pivot->amount)->toEqual(2000)
        ->and($payment->imputedInstallments->firstWhere('id', $this->installmentA->id)->pivot->amount)->toEqual(500)
        // La venta de B quedó saldada; la de A sigue con saldo.
        ->and($this->saleB->refresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->saleA->refresh()->status)->toBe(SaleStatus::Active)
        ->and($this->customer->accountBalance())->toBe('2500.00');
});

it('el cobro es un cobro suelto con sus copias del medio de pago', function () {
    $payment = receivePayment('1000.00', ['notes' => 'En el local']);

    expect($payment->sale_id)->toBeNull()
        ->and($payment->customer_id)->toBe($this->customer->id)
        ->and($payment->collected_by)->toBe($this->admin->id)
        ->and($payment->number())->toBe('C-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT))
        ->and($payment->notes)->toBe('En el local')
        ->and($payment->date->toDateString())->toBe('2026-11-16')
        ->and($payment->isOnAccount())->toBeTrue();
});

it('el recargo o descuento del medio no baja la deuda: la baja la base', function () {
    $discount = receivePayment('1000.00', ['payment_method_id' => $this->cash->id]);
    $surcharge = receivePayment('1000.00', ['payment_method_id' => $this->card->id]);

    expect($discount->base_amount)->toBe('1000.00')
        ->and($discount->adjustment_amount)->toBe('-100.00')
        ->and($discount->amount)->toBe('900.00')
        ->and($surcharge->adjustment_amount)->toBe('150.00')
        ->and($surcharge->amount)->toBe('1150.00')
        ->and($surcharge->installments)->toBe(3)
        ->and($this->customer->accountBalance())->toBe('3000.00');

    // El crédito en la cuenta es la base.
    expect(AccountMovement::query()->where('type', AccountMovementType::Payment)->orderBy('id')->pluck('credit')->all())
        ->toBe(['1000.00', '1000.00']);
});

it('deja el crédito en la cuenta del cliente con su saldo acumulado', function () {
    receivePayment('1500.00');

    $last = AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->firstOrFail();

    expect($last->type)->toBe(AccountMovementType::Payment)
        ->and($last->credit)->toBe('1500.00')
        ->and($last->debit)->toBe('0.00')
        ->and($last->balance)->toBe('3500.00')
        ->and($last->description)->toContain('C-')
        ->and($last->user_id)->toBe($this->admin->id);
});

it('admite un cobro adelantado a un vencimiento que todavía no venció, eligiéndolo', function () {
    receivePayment('1000.00', ['installment_ids' => [$this->installmentA->id]]);

    expect($this->installmentA->refresh()->paid_amount)->toBe('1000.00')
        ->and($this->installmentA->status)->toBe(InstallmentStatus::Partial)
        // El más viejo no se tocó.
        ->and($this->installmentB->refresh()->paid_amount)->toBe('0.00');
});

it('con vencimientos elegidos no puede superar su saldo ni tocar los de otro cliente', function () {
    expect(fn () => receivePayment('3000.01', ['installment_ids' => [$this->installmentA->id]]))
        ->toThrow(ValidationException::class, 'supera el saldo de los vencimientos elegidos');

    $other = Customer::factory()->create();
    $otherSale = collectSale(1, '2026-11-30', $other);

    expect(fn () => receivePayment('100.00', ['installment_ids' => [$otherSale->installments()->firstOrFail()->id]]))
        ->toThrow(ValidationException::class, 'no son de este cliente');

    expect(Installment::query()->sum('paid_amount'))->toEqual(0);
});

it('no se cobra más que la deuda ni montos inválidos', function (string|int|null $base) {
    expect(fn () => receivePayment((string) $base))->toThrow(ValidationException::class);

    expect(Installment::query()->sum('paid_amount'))->toEqual(0)
        ->and(AccountMovement::query()->where('type', AccountMovementType::Payment)->count())->toBe(0);
})->with(['más que la deuda' => '5000.01', 'cero' => '0', 'negativo' => '-10', 'no numérico' => 'abc', 'vacío' => '']);

it('se puede cobrar justo toda la deuda y las ventas pasan a pagadas', function () {
    receivePayment('5000.00');

    expect($this->saleA->refresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->saleB->refresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->customer->accountBalance())->toBe('0.00')
        ->and(Installment::query()->open()->count())->toBe(0);

    expect(fn () => receivePayment('1.00'))->toThrow(ValidationException::class, 'deuda');
});

it('el saldo del libro coincide con la suma de los vencimientos abiertos', function () {
    receivePayment('700.00');
    receivePayment('1300.50');
    receivePayment('100.00', ['installment_ids' => [$this->installmentA->id]]);

    $ledger = (string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance');

    expect($ledger)->toBe($this->customer->accountBalance())->and($ledger)->toBe('2899.50');
});

it('un cliente inactivo con deuda puede pagar', function () {
    $this->customer->update(['active' => false]);

    expect(receivePayment('1000.00')->exists)->toBeTrue();
});

it('el vendedor también registra cobros pero otro rol no', function () {
    expect(receivePayment('500.00', actor: $this->seller)->collected_by)->toBe($this->seller->id);

    $customerService = User::factory()->create(['role' => 'superadmin']);

    expect(fn () => receivePayment('500.00', actor: $customerService))->toThrow(AuthorizationException::class);
});

it('rechaza un cliente o un medio de pago inválidos y el módulo apagado', function () {
    expect(fn () => receivePayment('100.00', ['customer_id' => 9999]))->toThrow(ValidationException::class, 'cliente');

    $inactive = PaymentMethod::factory()->inactive()->create();

    expect(fn () => receivePayment('100.00', ['payment_method_id' => $inactive->id]))->toThrow(ValidationException::class, 'medio de pago');
    expect(fn () => receivePayment('100.00', ['payment_method_id' => 9999]))->toThrow(ValidationException::class, 'medio de pago');

    Modules::disable(Module::CurrentAccount);

    expect(fn () => receivePayment('100.00'))->toThrow(ValidationException::class, 'apagado');
});

it('registra el cobro en la actividad', function () {
    $payment = receivePayment('800.00');

    $log = ActivityLog::query()
        ->where('event', ActivityEvent::Created->value)
        ->where('subject_type', $payment->getMorphClass())
        ->where('subject_id', $payment->id)
        ->first();

    expect($log)->not->toBeNull()->and($log->description)->toContain($payment->number())->toContain('Marta Cliente');
});
