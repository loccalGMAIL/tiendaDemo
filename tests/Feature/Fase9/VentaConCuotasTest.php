<?php

use App\Actions\Accounts\CollectPayment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\AccountMovementType;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Models\AccountMovement;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\Installment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\InstallmentSchedule;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    // Costo 600 sobre precio 1.000: 10 unidades = 10.000 de venta y 6.000 de costo.
    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->none = PaymentMethod::factory()->create(['name' => 'Transferencia', 'adjustment_percent' => '0.00']);
    $this->plan = FinancingPlan::factory()->of(6, '12.00')->create(['name' => 'Seis cuotas']);
});

function planSale(array $data = []): Sale
{
    $test = test();

    return app(CreateSale::class)->handle($test->admin, [
        'customer_id' => $test->customer->id,
        'date' => '2026-11-16',
        'financing_plan_id' => $test->plan->id,
        'first_month' => '2026-12',
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 10]],
        ...$data,
    ]);
}

// --- La venta con un plan -----------------------------------------------------------------------

it('una venta con plan se financia con su recargo y se divide en cuotas', function () {
    $sale = planSale();

    // Sin anticipo: 10.000 a financiar + 12 % de recargo = 11.200 en 6 cuotas.
    expect($sale->total)->toBe('10000.00')
        ->and($sale->down_payment)->toBe('0.00')
        ->and($sale->financing_surcharge)->toBe('1200.00')
        ->and($sale->financing_surcharge_percent)->toBe('12.00')
        ->and($sale->financed_amount)->toBe('11200.00')
        ->and($sale->financing_plan_id)->toBe($this->plan->id)
        ->and($sale->installments_count)->toBe(6)
        ->and($sale->first_due_date->toDateString())->toBe('2026-12-10')
        ->and($sale->status)->toBe(SaleStatus::Active)
        // total − cobrado en el acto + recargo = lo financiado
        ->and(bcadd(bcsub($sale->total, $sale->down_payment, 2), $sale->financing_surcharge, 2))->toBe($sale->financed_amount);

    $installments = $sale->installments;

    expect($installments)->toHaveCount(6)
        ->and($installments->pluck('number')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and($installments->map(fn (Installment $i): string => $i->due_date->toDateString())->all())
        ->toBe(['2026-12-10', '2027-01-10', '2027-02-10', '2027-03-10', '2027-04-10', '2027-05-10'])
        // 11.200 ÷ 6 = 1.866,66…: cinco cuotas de 1.866,66 y la última absorbe 1.866,70.
        ->and($installments->pluck('amount')->all())->toBe(['1866.66', '1866.66', '1866.66', '1866.66', '1866.66', '1866.70'])
        ->and($installments->every(fn (Installment $i): bool => $i->status === InstallmentStatus::Pending && $i->paid_amount === '0.00'))->toBeTrue();
});

it('la porción de costo de las cuotas suma el costo de lo financiado', function () {
    $sale = planSale();

    // Toda la venta se financia: el costo repartido es el total (6.000), sin el recargo.
    expect(bcadd('0', (string) $sale->installments->sum(fn (Installment $i): string => $i->cost_portion), 2))->toBe('6000.00')
        ->and($sale->installments->pluck('cost_portion')->all())->toBe(['1000.00', '1000.00', '1000.00', '1000.00', '1000.00', '1000.00']);
});

it('con anticipo el recargo se calcula solo sobre lo que queda a cuenta', function () {
    $sale = planSale(['payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '4000.00']]]);

    // Efectivo −10 % sobre 4.000 = −400: total 9.600, cobrado 3.600, a cuenta 6.000 + 12 % = 6.720.
    expect($sale->total)->toBe('9600.00')
        ->and($sale->down_payment)->toBe('3600.00')
        ->and($sale->financing_surcharge)->toBe('720.00')
        ->and($sale->financed_amount)->toBe('6720.00')
        ->and($sale->installments->pluck('amount')->all())->toBe(['1120.00', '1120.00', '1120.00', '1120.00', '1120.00', '1120.00'])
        // Costo de lo financiado: 6.000 × 6.000 / 10.000.
        ->and(bcadd('0', (string) $sale->installments->sum(fn (Installment $i): string => $i->cost_portion), 2))->toBe('3600.00');
});

it('un plan sin recargo financia exactamente lo que queda a cuenta', function () {
    $free = FinancingPlan::factory()->of(3)->create();

    $sale = planSale(['financing_plan_id' => $free->id]);

    expect($sale->financing_surcharge)->toBe('0.00')
        ->and($sale->financed_amount)->toBe('10000.00')
        ->and($sale->installments->pluck('amount')->all())->toBe(['3333.33', '3333.33', '3333.34'])
        // Sin recargo no hay movimiento de recargo.
        ->and(AccountMovement::query()->where('description', 'like', 'Recargo%')->count())->toBe(0);
});

it('la cuenta del cliente lleva el recargo aparte y coincide con los vencimientos', function () {
    $sale = planSale();

    $movements = AccountMovement::query()->where('customer_id', $this->customer->id)->orderBy('id')->get();

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->type)->toBe(AccountMovementType::Sale)
        ->and($movements[0]->debit)->toBe('10000.00')
        ->and($movements[1]->type)->toBe(AccountMovementType::Adjustment)
        ->and($movements[1]->debit)->toBe('1200.00')
        ->and($movements[1]->description)->toContain('Recargo de financiación')->toContain('6 cuotas')
        ->and($movements[1]->balance)->toBe('11200.00')
        ->and($this->customer->accountBalance())->toBe('11200.00')
        ->and($sale->exists)->toBeTrue();
});

it('el cronograma de la venta es el que arma el servicio con los mismos datos', function () {
    $sale = planSale(['payments' => [['payment_method_id' => $this->none->id, 'base_amount' => '2500.00']]]);

    $service = new InstallmentSchedule;
    $rows = $service->build(
        (string) $sale->financed_amount,
        (int) $sale->installments_count,
        $sale->first_due_date,
        $service->anchorDay($sale->date, 'fixed_day', 10),
        $service->financedCost((string) $sale->total_cost, bcsub((string) $sale->financed_amount, (string) $sale->financing_surcharge, 2), (string) $sale->subtotal),
    );

    expect($sale->installments->map(fn (Installment $i): array => [
        'number' => $i->number,
        'due_date' => $i->due_date->toDateString(),
        'amount' => $i->amount,
        'cost_portion' => $i->cost_portion,
    ])->all())->toBe(array_map(fn (array $row): array => [
        'number' => $row['number'],
        'due_date' => $row['due_date']->toDateString(),
        'amount' => $row['amount'],
        'cost_portion' => $row['cost_portion'],
    ], $rows));
});

// --- La venta copia el plan ------------------------------------------------------------------------

it('cambiar o desactivar el plan después no afecta a la venta ya hecha', function () {
    $sale = planSale();

    $this->plan->update(['installments_count' => 12, 'surcharge_percent' => '50.00', 'active' => false]);

    $sale->refresh();

    expect($sale->installments_count)->toBe(6)
        ->and($sale->financing_surcharge_percent)->toBe('12.00')
        ->and($sale->financing_surcharge)->toBe('1200.00')
        ->and($sale->financed_amount)->toBe('11200.00')
        ->and($sale->installments()->count())->toBe(6)
        ->and($sale->financingPlan->installments_count)->toBe(12);
});

// --- Reglas de vencimiento ---------------------------------------------------------------------------

it('con la regla «mismo día de la venta» las cuotas vencen el día de la venta', function () {
    Modules::updateSettings(Module::Installments, ['due_rule' => 'same_day']);

    $sale = planSale(['date' => '2026-01-31', 'first_month' => null, 'financing_plan_id' => FinancingPlan::factory()->of(4)->create()->id]);

    expect($sale->first_due_date->toDateString())->toBe('2026-02-28')
        ->and($sale->installments->map(fn (Installment $i): string => $i->due_date->toDateString())->all())
        ->toBe(['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31']);
});

it('con día fijo y sin elegir mes la primera cuota es la del mes siguiente', function () {
    Modules::updateSettings(Module::Installments, ['due_rule' => 'fixed_day', 'fixed_day' => 5]);

    $sale = planSale(['first_month' => null]);

    expect($sale->first_due_date->toDateString())->toBe('2026-12-05');
});

// --- Sin plan y sin el módulo ----------------------------------------------------------------------------

it('sin elegir plan el saldo es un único vencimiento con la fecha indicada', function () {
    $sale = planSale(['financing_plan_id' => null, 'first_month' => null, 'due_date' => '2027-01-20']);

    expect($sale->financing_plan_id)->toBeNull()
        ->and($sale->installments_count)->toBeNull()
        ->and($sale->financing_surcharge)->toBe('0.00')
        ->and($sale->financed_amount)->toBe('10000.00')
        ->and($sale->installments)->toHaveCount(1)
        ->and($sale->installments->first()->due_date->toDateString())->toBe('2027-01-20')
        ->and($sale->installments->first()->amount)->toBe('10000.00')
        ->and($sale->installments->first()->cost_portion)->toBe('6000.00');

    expect(fn () => planSale(['financing_plan_id' => null, 'first_month' => null]))
        ->toThrow(ValidationException::class, 'vencimiento');
});

it('con el módulo apagado el saldo es un único vencimiento y el plan se ignora', function () {
    Modules::disable(Module::Installments);

    $sale = planSale(['due_date' => '2027-01-20']);

    expect($sale->financing_plan_id)->toBeNull()
        ->and($sale->financing_surcharge)->toBe('0.00')
        ->and($sale->financed_amount)->toBe('10000.00')
        ->and($sale->installments)->toHaveCount(1)
        ->and($sale->installments->first()->due_date->toDateString())->toBe('2027-01-20');

    // Y sin vencimiento no hay venta: el plan no lo reemplaza.
    expect(fn () => planSale())->toThrow(ValidationException::class, 'vencimiento');
});

it('una venta cobrada entera ignora el plan', function () {
    $sale = planSale(['payments' => [['payment_method_id' => $this->none->id]]]);

    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->financing_plan_id)->toBeNull()
        ->and($sale->installments)->toHaveCount(0)
        ->and(AccountMovement::count())->toBe(0);
});

// --- Validaciones ----------------------------------------------------------------------------------------------

it('rechaza un plan inexistente o inactivo, y un mes de la primera cuota inválido', function (array $override, string $field) {
    $override = array_map(fn (mixed $value): mixed => $value === '__inactive__' ? FinancingPlan::factory()->inactive()->create()->id : $value, $override);

    expect(fn () => planSale($override))->toThrow(ValidationException::class);

    try {
        planSale($override);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(Sale::count())->toBe(0)->and(Installment::count())->toBe(0);
})->with([
    'plan inexistente' => [['financing_plan_id' => 9999], 'financing_plan_id'],
    'plan inactivo' => [['financing_plan_id' => '__inactive__'], 'financing_plan_id'],
    'mes con mal formato' => [['first_month' => '12/2026'], 'first_month'],
    'mes que no existe' => [['first_month' => '2026-13'], 'first_month'],
    'mes anterior al de la venta' => [['first_month' => '2026-10'], 'first_month'],
    // Venta el 20 con vencimiento el día 10 del mismo mes: la primera cuota quedaría antes de la venta.
    'primera cuota antes de la venta' => [['date' => '2026-11-20', 'first_month' => '2026-11'], 'first_month'],
]);

// --- Cobros y anulaciones ----------------------------------------------------------------------------------------

it('un cobro cancela las cuotas del más viejo al más nuevo y la venta se salda al pagar la última', function () {
    $sale = planSale();

    app(CollectPayment::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'payment_method_id' => $this->none->id,
        'base_amount' => '4000.00',
        'date' => '2026-11-16',
    ]);

    $installments = $sale->installments()->get();

    // 4.000 = 2 cuotas completas (3.733,32) + 266,68 de la tercera.
    expect($installments[0]->status)->toBe(InstallmentStatus::Paid)
        ->and($installments[1]->status)->toBe(InstallmentStatus::Paid)
        ->and($installments[2]->status)->toBe(InstallmentStatus::Partial)
        ->and($installments[2]->paid_amount)->toBe('266.68')
        ->and($installments[3]->status)->toBe(InstallmentStatus::Pending)
        ->and($sale->refresh()->status)->toBe(SaleStatus::Active)
        ->and($this->customer->accountBalance())->toBe('7200.00');

    app(CollectPayment::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'payment_method_id' => $this->none->id,
        'base_amount' => '7200.00',
    ]);

    expect($sale->refresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->customer->accountBalance())->toBe('0.00')
        ->and((string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance'))->toBe('0.00');
});

it('el saldo del libro coincide con los vencimientos después de cobrar', function () {
    planSale();
    planSale(['date' => '2026-11-10', 'first_month' => '2026-11', 'financing_plan_id' => FinancingPlan::factory()->of(2)->create()->id]);

    app(CollectPayment::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'payment_method_id' => $this->none->id,
        'base_amount' => '2345.67',
    ]);

    $ledger = (string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance');

    expect($ledger)->toBe($this->customer->accountBalance())->and($ledger)->toBe('18854.33');
});

it('anular la venta deja la cuenta en cero, recargo incluido', function () {
    $sale = planSale(['payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '4000.00']]]);

    expect($this->customer->accountBalance())->toBe('6720.00');

    app(CancelSale::class)->handle($sale, $this->admin, 'Se devolvió');

    expect($this->customer->accountBalance())->toBe('0.00')
        ->and((string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance'))->toBe('0.00')
        ->and($sale->installments()->where('status', InstallmentStatus::Cancelled->value)->count())->toBe(6)
        ->and(AccountMovement::query()->where('description', 'like', 'Anulación del recargo%')->count())->toBe(1);
});

it('no se anula una venta en cuotas que tiene cobros imputados', function () {
    $sale = planSale();

    app(CollectPayment::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'payment_method_id' => $this->none->id,
        'base_amount' => '100.00',
    ]);

    expect(fn () => app(CancelSale::class)->handle($sale, $this->admin, 'Motivo'))
        ->toThrow(ValidationException::class, 'cobros imputados');

    expect($sale->refresh()->status)->toBe(SaleStatus::Active)->and($sale->installments()->count())->toBe(6);
});
