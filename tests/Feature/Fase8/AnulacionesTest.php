<?php

use App\Actions\Accounts\CancelPayment;
use App\Actions\Accounts\CollectPayment;
use App\Actions\Sales\CancelSale;
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
});

/**
 * Venta de 5.000 con 2.000 cobrados en el acto (efectivo, −10 %: entran 1.800) y 3.000 a cuenta.
 */
function cancellableSale(): Sale
{
    $test = test();

    return app(CreateSale::class)->handle($test->admin, [
        'customer_id' => $test->customer->id,
        'date' => '2026-11-01',
        'due_date' => '2026-12-01',
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 5]],
        'payments' => [['payment_method_id' => $test->cash->id, 'base_amount' => '2000.00']],
    ]);
}

function payOnAccount(string $base): Payment
{
    $test = test();

    return app(CollectPayment::class)->handle($test->admin, [
        'customer_id' => $test->customer->id,
        'payment_method_id' => $test->none->id,
        'base_amount' => $base,
        'date' => '2026-11-16',
    ]);
}

// --- Anular un cobro -------------------------------------------------------------------------

it('anular un cobro devuelve lo imputado, reabre la venta y deja un débito de ajuste', function () {
    $sale = cancellableSale();
    $payment = payOnAccount('3000.00');

    expect($sale->refresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->customer->accountBalance())->toBe('0.00');

    $cancelled = app(CancelPayment::class)->handle($payment, $this->admin, 'Cheque rechazado');

    $installment = $sale->installments()->firstOrFail();

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($this->admin->id)
        ->and($cancelled->cancellation_reason)->toBe('Cheque rechazado')
        ->and($installment->paid_amount)->toBe('0.00')
        ->and($installment->status)->toBe(InstallmentStatus::Pending)
        ->and($sale->refresh()->status)->toBe(SaleStatus::Active)
        ->and($this->customer->accountBalance())->toBe('3000.00');

    $last = AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->firstOrFail();

    expect($last->type)->toBe(AccountMovementType::Adjustment)
        ->and($last->debit)->toBe('3000.00')
        ->and($last->balance)->toBe('3000.00')
        ->and($last->description)->toContain('Cheque rechazado')
        ->and($last->balance)->toBe($this->customer->accountBalance());
});

it('anular un cobro parcial deja el vencimiento con el pago que le queda', function () {
    $sale = cancellableSale();
    payOnAccount('1000.00');
    $second = payOnAccount('500.00');

    app(CancelPayment::class)->handle($second, $this->admin, 'Error de carga');

    $installment = $sale->installments()->firstOrFail();

    expect($installment->paid_amount)->toBe('1000.00')
        ->and($installment->status)->toBe(InstallmentStatus::Partial)
        ->and($sale->refresh()->status)->toBe(SaleStatus::Active)
        ->and($this->customer->accountBalance())->toBe('2000.00');
});

it('un cobro anulado no se puede anular otra vez y el motivo es obligatorio', function () {
    cancellableSale();
    $payment = payOnAccount('1000.00');

    expect(fn () => app(CancelPayment::class)->handle($payment, $this->admin, '   '))->toThrow(ValidationException::class, 'motivo');

    app(CancelPayment::class)->handle($payment, $this->admin, 'Error');

    expect(fn () => app(CancelPayment::class)->handle($payment, $this->admin, 'Otra vez'))->toThrow(ValidationException::class, 'ya está anulado');
});

it('solo el administrador anula cobros y con el módulo encendido', function () {
    cancellableSale();
    $payment = payOnAccount('1000.00');

    expect(fn () => app(CancelPayment::class)->handle($payment, $this->seller, 'Motivo'))->toThrow(AuthorizationException::class);

    Modules::disable(Module::CurrentAccount);

    expect(fn () => app(CancelPayment::class)->handle($payment, $this->admin, 'Motivo'))->toThrow(ValidationException::class, 'apagado');
});

it('el cobro hecho en el acto de la venta no se anula solo', function () {
    $sale = cancellableSale();
    $upfront = $sale->payments()->firstOrFail();

    expect(fn () => app(CancelPayment::class)->handle($upfront, $this->admin, 'Motivo'))
        ->toThrow(ValidationException::class, 'se anula con la venta');

    expect($upfront->refresh()->isCancelled())->toBeFalse()
        ->and($this->admin->can('cancel', $upfront))->toBeFalse();
});

// --- Anular una venta ------------------------------------------------------------------------

it('no se anula una venta que tiene cobros imputados hasta anular esos cobros', function () {
    $sale = cancellableSale();
    $payment = payOnAccount('1000.00');

    expect(fn () => app(CancelSale::class)->handle($sale, $this->admin, 'Se devolvió'))
        ->toThrow(ValidationException::class, 'cobros imputados');

    // Todo quedó como estaba: nada se anuló a medias.
    expect($sale->refresh()->status)->toBe(SaleStatus::Active)
        ->and($sale->payments()->whereNull('cancelled_at')->count())->toBe(1)
        ->and($sale->installments()->firstOrFail()->status)->toBe(InstallmentStatus::Partial);

    app(CancelPayment::class)->handle($payment, $this->admin, 'Error de carga');

    expect(app(CancelSale::class)->handle($sale, $this->admin, 'Se devolvió')->status)->toBe(SaleStatus::Cancelled);
});

it('anular una venta anula sus vencimientos y la cuenta del cliente queda en cero', function () {
    $sale = cancellableSale();

    // 5.000 − 200 (descuento) = 4.800 de total; cobrado 1.800; a cuenta 3.000.
    expect($sale->total)->toBe('4800.00')->and($this->customer->accountBalance())->toBe('3000.00');

    app(CancelSale::class)->handle($sale, $this->admin, 'Se devolvió');

    expect($sale->installments()->firstOrFail()->status)->toBe(InstallmentStatus::Cancelled)
        ->and($this->customer->accountBalance())->toBe('0.00')
        ->and((string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance'))->toBe('0.00');

    $adjustments = AccountMovement::query()->where('type', AccountMovementType::Adjustment)->orderBy('id')->get();

    // Crédito por el total y débito por lo cobrado en el acto.
    expect($adjustments)->toHaveCount(2)
        ->and($adjustments[0]->credit)->toBe('4800.00')
        ->and($adjustments[1]->debit)->toBe('1800.00');
});

it('anular una venta de contado o con el módulo apagado después sigue funcionando', function () {
    $cash = app(CreateSale::class)->handle($this->admin, [
        'customer_id' => $this->customer->id,
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $this->none->id]],
    ]);

    expect(app(CancelSale::class)->handle($cash, $this->admin, 'Error')->status)->toBe(SaleStatus::Cancelled)
        ->and(AccountMovement::count())->toBe(0);

    $onAccount = cancellableSale();

    Modules::disable(Module::CurrentAccount);

    app(CancelSale::class)->handle($onAccount, $this->admin, 'Se devolvió');

    expect($onAccount->installments()->firstOrFail()->status)->toBe(InstallmentStatus::Cancelled)
        ->and((string) AccountMovement::query()->where('customer_id', $this->customer->id)->orderByDesc('id')->value('balance'))->toBe('0.00');
});

it('los vencimientos de una venta anulada no cuentan como deuda ni se cobran', function () {
    $sale = cancellableSale();
    $other = cancellableSale();
    app(CancelSale::class)->handle($sale, $this->admin, 'Se devolvió');

    expect($this->customer->accountBalance())->toBe('3000.00')
        ->and(Installment::query()->open()->count())->toBe(1);

    expect(fn () => payOnAccount('3000.01'))->toThrow(ValidationException::class, 'deuda');

    payOnAccount('3000.00');

    expect($other->refresh()->status)->toBe(SaleStatus::Paid);
});

it('las anulaciones quedan en la actividad', function () {
    $sale = cancellableSale();
    $payment = payOnAccount('500.00');
    app(CancelPayment::class)->handle($payment, $this->admin, 'Error de carga');

    $cancellations = ActivityLog::query()->where('event', ActivityEvent::Cancelled->value)->pluck('description');

    expect($cancellations->contains(fn (string $text) => str_starts_with($text, 'Anulación de cobro')))->toBeTrue()
        ->and($sale->exists)->toBeTrue();
});
