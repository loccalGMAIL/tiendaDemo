<?php

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\ActivityEvent;
use App\Enums\SaleStatus;
use App\Events\SaleCancelled;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $product = Product::factory()->priced('1000.00', '600.00')->create();
    $cash = PaymentMethod::factory()->create(['adjustment_percent' => '-10.00']);
    $card = PaymentMethod::factory()->create(['installments' => 3, 'adjustment_percent' => '15.00']);

    $this->sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => 3]],
        'payments' => [
            ['payment_method_id' => $cash->id, 'base_amount' => '1000.00'],
            ['payment_method_id' => $card->id, 'base_amount' => '2000.00'],
        ],
    ]);
});

it('anula la venta con motivo y deja quién, cuándo y por qué', function () {
    $this->travelTo(now()->addDay());

    $sale = app(CancelSale::class)->handle($this->sale, $this->admin, '  Error de carga  ');

    expect($sale->status)->toBe(SaleStatus::Cancelled)
        ->and($sale->isCancelled())->toBeTrue()
        ->and($sale->cancelled_by)->toBe($this->admin->id)
        ->and($sale->cancellation_reason)->toBe('Error de carga')
        ->and($sale->cancelled_at->isSameDay(now()))->toBeTrue()
        ->and($this->sale->fresh()->status)->toBe(SaleStatus::Cancelled);
});

it('anula también todos los cobros de la venta con el mismo motivo', function () {
    app(CancelSale::class)->handle($this->sale, $this->admin, 'Devolución');

    $payments = $this->sale->payments()->get();

    expect($payments)->toHaveCount(2);

    foreach ($payments as $payment) {
        expect($payment->isCancelled())->toBeTrue()
            ->and($payment->cancelled_by)->toBe($this->admin->id)
            ->and($payment->cancellation_reason)->toBe('Devolución');
    }
});

it('conserva los importes y los ítems en el historial', function () {
    $total = $this->sale->total;

    app(CancelSale::class)->handle($this->sale, $this->admin, 'Devolución');

    $sale = $this->sale->fresh();

    expect($sale->total)->toBe($total)
        ->and($sale->items()->count())->toBe(1)
        ->and($sale->payments()->count())->toBe(2);
});

it('el motivo es obligatorio', function (string $reason) {
    expect(fn () => app(CancelSale::class)->handle($this->sale, $this->admin, $reason))
        ->toThrow(ValidationException::class, 'motivo');

    expect($this->sale->fresh()->status)->toBe(SaleStatus::Paid)
        ->and($this->sale->payments()->whereNotNull('cancelled_at')->count())->toBe(0);
})->with(['', '   ']);

it('no se anula dos veces', function () {
    app(CancelSale::class)->handle($this->sale, $this->admin, 'Primera');

    expect(fn () => app(CancelSale::class)->handle($this->sale, $this->admin, 'Segunda'))
        ->toThrow(ValidationException::class, 'ya está anulada');

    expect($this->sale->fresh()->cancellation_reason)->toBe('Primera');
});

it('solo un administrador anula', function () {
    expect(fn () => app(CancelSale::class)->handle($this->sale, $this->seller, 'Quiero anularla'))
        ->toThrow(AuthorizationException::class);

    expect($this->sale->fresh()->status)->toBe(SaleStatus::Paid);
});

it('dispara SaleCancelled y registra la anulación en la actividad', function () {
    Event::fake([SaleCancelled::class]);

    app(CancelSale::class)->handle($this->sale, $this->admin, 'Error de carga');

    Event::assertDispatched(SaleCancelled::class, fn (SaleCancelled $event) => $event->sale->is($this->sale));

    $log = ActivityLog::where('event', ActivityEvent::Cancelled)->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->description)->toContain($this->sale->number())->toContain('Error de carga')
        ->and($log->properties['reason'])->toBe('Error de carga');
});

it('no dispara SaleCancelled cuando la anulación falla', function () {
    Event::fake([SaleCancelled::class]);

    try {
        app(CancelSale::class)->handle($this->sale, $this->admin, '');
    } catch (ValidationException) {
    }

    Event::assertNotDispatched(SaleCancelled::class);
});

it('las ventas anuladas no cuentan como vigentes', function () {
    $other = Sale::factory()->create();

    app(CancelSale::class)->handle($this->sale, $this->admin, 'Devolución');

    expect(Sale::query()->notCancelled()->pluck('id')->all())->toBe([$other->id]);
});

it('la política deja anular solo al administrador y solo una vez', function () {
    expect($this->admin->can('cancel', $this->sale))->toBeTrue()
        ->and($this->seller->can('cancel', $this->sale))->toBeFalse();

    app(CancelSale::class)->handle($this->sale, $this->admin, 'Devolución');

    expect($this->admin->can('cancel', $this->sale->fresh()))->toBeFalse();
});

it('nadie edita ni borra una venta', function () {
    expect($this->admin->can('update', $this->sale))->toBeFalse()
        ->and($this->admin->can('delete', $this->sale))->toBeFalse()
        ->and($this->seller->can('update', $this->sale))->toBeFalse()
        ->and($this->seller->can('delete', $this->sale))->toBeFalse();
});
