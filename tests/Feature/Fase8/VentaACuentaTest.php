<?php

use App\Actions\Sales\CreateSale;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
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

    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00']);
});

function onAccountSale(array $data = []): array
{
    $test = test();

    return [
        'customer_id' => $test->customer->id,
        'date' => '2026-11-16',
        'due_date' => '2026-12-16',
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 10]],
        ...$data,
    ];
}

it('una venta con anticipo deja el saldo a cuenta con su vencimiento', function () {
    $sale = app(CreateSale::class)->handle($this->admin, onAccountSale([
        'payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '4000.00']],
    ]));

    // 10 × 1000 = 10.000; efectivo −10 % sobre 4.000 = −400 → total 9.600, cobrado 3.600, a cuenta 6.000.
    expect($sale->subtotal)->toBe('10000.00')
        ->and($sale->adjustment)->toBe('-400.00')
        ->and($sale->total)->toBe('9600.00')
        ->and($sale->down_payment)->toBe('3600.00')
        ->and($sale->financed_amount)->toBe('6000.00')
        ->and($sale->first_due_date->toDateString())->toBe('2026-12-16')
        ->and($sale->status)->toBe(SaleStatus::Active)
        ->and($sale->payments)->toHaveCount(1)
        ->and($sale->payments->first()->amount)->toBe('3600.00');

    $installment = Installment::query()->firstOrFail();

    expect($installment->sale_id)->toBe($sale->id)
        ->and($installment->customer_id)->toBe($this->customer->id)
        ->and($installment->number)->toBe(1)
        ->and($installment->due_date->toDateString())->toBe('2026-12-16')
        ->and($installment->amount)->toBe('6000.00')
        ->and($installment->paid_amount)->toBe('0.00')
        // Costo total 6.000 × 6.000 / 10.000.
        ->and($installment->cost_portion)->toBe('3600.00')
        ->and($installment->status)->toBe(InstallmentStatus::Pending);

    $movements = AccountMovement::query()->where('customer_id', $this->customer->id)->orderBy('id')->get();

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->type)->toBe(AccountMovementType::Sale)
        ->and($movements[0]->debit)->toBe('9600.00')
        ->and($movements[0]->balance)->toBe('9600.00')
        ->and($movements[1]->type)->toBe(AccountMovementType::Payment)
        ->and($movements[1]->credit)->toBe('3600.00')
        ->and($movements[1]->balance)->toBe('6000.00')
        ->and($this->customer->accountBalance())->toBe('6000.00');
});

it('el total menos lo cobrado en el acto es lo que queda a cuenta', function () {
    $sale = app(CreateSale::class)->handle($this->seller, onAccountSale([
        'payments' => [['payment_method_id' => $this->card->id, 'base_amount' => '2000.00']],
    ]));

    // Tarjeta +15 % sobre 2.000 = +300.
    expect($sale->total)->toBe('10300.00')
        ->and($sale->down_payment)->toBe('2300.00')
        ->and($sale->financed_amount)->toBe('8000.00')
        ->and(bcsub($sale->total, $sale->down_payment, 2))->toBe($sale->financed_amount);
});

it('una venta entera a cuenta no lleva cobros', function () {
    $sale = app(CreateSale::class)->handle($this->admin, onAccountSale());

    expect($sale->payments)->toHaveCount(0)
        ->and($sale->total)->toBe('10000.00')
        ->and($sale->down_payment)->toBe('0.00')
        ->and($sale->financed_amount)->toBe('10000.00')
        ->and($sale->status)->toBe(SaleStatus::Active);

    $movements = AccountMovement::query()->get();

    expect($movements)->toHaveCount(1)
        ->and($movements->first()->debit)->toBe('10000.00')
        ->and($movements->first()->balance)->toBe('10000.00');
});

it('con saldo el cliente y el vencimiento son obligatorios y el vencimiento no puede ser anterior', function (array $override, string $field) {
    expect(fn () => app(CreateSale::class)->handle($this->admin, onAccountSale($override)))
        ->toThrow(ValidationException::class);

    try {
        app(CreateSale::class)->handle($this->admin, onAccountSale($override));
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(Installment::count())->toBe(0);
})->with([
    'sin cliente' => [['customer_id' => null], 'customer_id'],
    'sin vencimiento' => [['due_date' => null], 'due_date'],
    'vencimiento anterior a la venta' => [['due_date' => '2026-11-15'], 'due_date'],
    'vencimiento inválido' => [['due_date' => 'mañana'], 'due_date'],
]);

it('los cobros no pueden superar el subtotal', function () {
    expect(fn () => app(CreateSale::class)->handle($this->admin, onAccountSale([
        'payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '10001.00']],
    ])))->toThrow(ValidationException::class, 'supera el subtotal');
});

it('una venta cobrada entera, aunque haya módulo, es de contado y no genera movimientos', function () {
    $sale = app(CreateSale::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
        'payments' => [['payment_method_id' => $this->cash->id]],
    ]);

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->financed_amount)->toBe('0.00')
        ->and($sale->first_due_date)->toBeNull()
        ->and(Installment::count())->toBe(0)
        ->and(AccountMovement::count())->toBe(0);
});

it('sin el módulo toda venta es de contado', function () {
    Modules::disable(Module::CurrentAccount);

    expect(fn () => app(CreateSale::class)->handle($this->admin, onAccountSale([
        'payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '4000.00']],
    ])))->toThrow(ValidationException::class, 'no coincide con el subtotal');

    expect(fn () => app(CreateSale::class)->handle($this->admin, onAccountSale()))
        ->toThrow(ValidationException::class, 'al menos un medio de pago');

    $sale = app(CreateSale::class)->handle($this->admin, onAccountSale([
        'payments' => [['payment_method_id' => $this->cash->id]],
    ]));

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->financed_amount)->toBe('0.00')
        ->and(Installment::count())->toBe(0)
        ->and(AccountMovement::count())->toBe(0);
});

it('la venta a cuenta descuenta el stock como cualquier otra', function () {
    Modules::enable(Module::Stock);

    app(CreateSale::class)->handle($this->admin, onAccountSale());

    expect($this->variant->refresh()->stock)->toBe(-10);
});
