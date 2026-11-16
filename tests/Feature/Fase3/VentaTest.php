<?php

use App\Actions\Sales\CreateSale;
use App\Enums\ActivityEvent;
use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00']);
});

function item(mixed $variant, int|string|null $quantity = 1, mixed $price = null): array
{
    return ['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_price' => $price];
}

function pay(mixed $method, string|int|null $base = null): array
{
    return ['payment_method_id' => $method->id, 'base_amount' => $base];
}

it('confirma una venta de contado que nace pagada con su cobro', function () {
    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant, 2)],
        'payments' => [pay($this->cash)],
    ]);

    // 2 × 1000 = 2000; efectivo −10 % = −200 → 1800
    expect($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->subtotal)->toBe('2000.00')
        ->and($sale->adjustment)->toBe('-200.00')
        ->and($sale->total)->toBe('1800.00')
        ->and($sale->down_payment)->toBe('1800.00')
        ->and($sale->financed_amount)->toBe('0.00')
        ->and($sale->payments)->toHaveCount(1)
        ->and($sale->payments->first()->amount)->toBe('1800.00')
        ->and($sale->payments->first()->collected_by)->toBe($this->seller->id);
});

it('copia precio, costo y descripción al vender', function () {
    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant, 3)],
        'payments' => [pay($this->card)],
    ]);

    $line = $sale->items->first();

    expect($line->description)->toBe('Remera')
        ->and($line->quantity)->toBe(3)
        ->and($line->unit_price)->toBe('1000.00')
        ->and($line->unit_cost)->toBe('600.00')
        ->and($line->subtotal)->toBe('3000.00')
        ->and($sale->total_cost)->toBe('1800.00');

    // Los cambios posteriores del catálogo no afectan la venta.
    $this->product->update(['price' => '5000.00', 'cost' => '4000.00', 'name' => 'Otro nombre']);

    $line = $line->fresh();
    expect($line->unit_price)->toBe('1000.00')
        ->and($line->unit_cost)->toBe('600.00')
        ->and($line->description)->toBe('Remera')
        ->and($sale->fresh()->total_cost)->toBe('1800.00');
});

it('usa el precio de la variante cuando tiene uno propio y arma su descripción', function () {
    $named = $this->product->variants()->create(['name' => 'M / Negro', 'price' => '1200.00', 'position' => 1]);

    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($named)],
        'payments' => [pay($this->cash)],
    ]);

    expect($sale->items->first()->unit_price)->toBe('1200.00')
        ->and($sale->items->first()->description)->toBe('Remera — M / Negro');
});

it('el cobro copia las cuotas y el porcentaje del medio, y no cambian después', function () {
    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->card)],
    ]);

    $payment = $sale->payments->first();

    // 1000 × 15 % = 150 → cobra 1150 en 3 cuotas con tarjeta
    expect($payment->installments)->toBe(3)
        ->and($payment->adjustment_percent)->toBe('15.00')
        ->and($payment->base_amount)->toBe('1000.00')
        ->and($payment->adjustment_amount)->toBe('150.00')
        ->and($payment->amount)->toBe('1150.00')
        ->and($sale->total)->toBe('1150.00');

    $this->card->update(['installments' => 6, 'adjustment_percent' => '99.00']);

    $payment = $payment->fresh();
    expect($payment->installments)->toBe(3)
        ->and($payment->adjustment_percent)->toBe('15.00')
        ->and($payment->amount)->toBe('1150.00');
});

it('redondea el recargo a centavos', function () {
    $product = Product::factory()->priced('3.33', '1.00')->create();

    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($product->implicitVariant)],
        'payments' => [pay($this->card)],
    ]);

    // 3.33 × 15 % = 0.4995 → 0.50
    expect($sale->adjustment)->toBe('0.50')
        ->and($sale->total)->toBe('3.83');
});

it('admite pago combinado: cada medio con su ajuste y la suma de bases igual al subtotal', function () {
    $sale = app(CreateSale::class)->handle($this->admin, [
        'items' => [item($this->variant, 3)],
        'payments' => [pay($this->cash, '1000.00'), pay($this->card, '2000.00')],
    ]);

    // efectivo: −100 → 900; tarjeta: +300 → 2300; total 3200
    expect($sale->payments)->toHaveCount(2)
        ->and($sale->payments[0]->amount)->toBe('900.00')
        ->and($sale->payments[1]->amount)->toBe('2300.00')
        ->and($sale->adjustment)->toBe('200.00')
        ->and($sale->total)->toBe('3200.00')
        ->and($sale->down_payment)->toBe('3200.00');
});

it('rechaza el pago combinado cuando la suma de bases no da el subtotal', function () {
    expect(fn () => app(CreateSale::class)->handle($this->admin, [
        'items' => [item($this->variant, 3)],
        'payments' => [pay($this->cash, '1000.00'), pay($this->card, '1000.00')],
    ]))->toThrow(ValidationException::class, 'no coincide con el subtotal');

    expect(Sale::count())->toBe(0);
});

it('con varios cobros exige indicar el monto de cada uno', function () {
    try {
        app(CreateSale::class)->handle($this->admin, [
            'items' => [item($this->variant, 2)],
            'payments' => [pay($this->cash), pay($this->card, '1000.00')],
        ]);
        $this->fail('Tenía que fallar');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('payments.0.base_amount');
    }
});

it('exige al menos un ítem y al menos un cobro', function () {
    expect(fn () => app(CreateSale::class)->handle($this->seller, ['items' => [], 'payments' => [pay($this->cash)]]))
        ->toThrow(ValidationException::class, 'al menos un ítem');

    expect(fn () => app(CreateSale::class)->handle($this->seller, ['items' => [item($this->variant)], 'payments' => []]))
        ->toThrow(ValidationException::class, 'al menos un medio de pago');
});

it('rechaza productos inactivos', function () {
    $this->product->update(['active' => false]);

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'inactivo');

    expect(Sale::count())->toBe(0);
});

it('rechaza variantes inactivas y variantes que no existen', function () {
    $this->variant->update(['active' => false]);

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'inactivo');

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [['variant_id' => 999999, 'quantity' => 1]],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'no existe');
});

it('rechaza cantidades menores a 1 o que no son enteras', function (mixed $quantity) {
    try {
        app(CreateSale::class)->handle($this->seller, [
            'items' => [item($this->variant, $quantity)],
            'payments' => [pay($this->cash)],
        ]);
        $this->fail('Tenía que fallar');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('items.0.quantity');
    }
})->with([0, -1, '0', '1.5', 'abc', null]);

it('rechaza montos negativos', function () {
    $other = Product::factory()->create();

    try {
        app(CreateSale::class)->handle($this->admin, [
            'items' => [item($other->implicitVariant, 1, '-5.00')],
            'payments' => [pay($this->cash)],
        ]);
        $this->fail('Tenía que fallar');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('items.0.unit_price');
    }

    try {
        app(CreateSale::class)->handle($this->admin, [
            'items' => [item($this->variant, 2)],
            'payments' => [pay($this->cash, '-1.00'), pay($this->card, '2001.00')],
        ]);
        $this->fail('Tenía que fallar');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('payments.0.base_amount');
    }
});

it('rechaza medios de pago inactivos o inexistentes', function () {
    $this->cash->update(['active' => false]);

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'medio de pago');

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [['payment_method_id' => 999999]],
    ]))->toThrow(ValidationException::class, 'medio de pago');
});

it('el vendedor no puede cambiar el precio; el administrador sí', function () {
    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant, 1, '1.00')],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'Solo un administrador puede cambiar el precio');

    // El mismo precio del catálogo no es un cambio.
    $same = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant, 1, '1000.00')],
        'payments' => [pay($this->cash)],
    ]);
    expect($same->items->first()->unit_price)->toBe('1000.00');

    $changed = app(CreateSale::class)->handle($this->admin, [
        'items' => [item($this->variant, 1, '800.00')],
        'payments' => [pay($this->cash)],
    ]);
    expect($changed->items->first()->unit_price)->toBe('800.00')
        ->and($changed->total)->toBe('720.00');
});

it('un vendedor siempre carga a su nombre, aunque indique otro', function () {
    $other = User::factory()->seller()->create();

    $sale = app(CreateSale::class)->handle($this->seller, [
        'seller_id' => $other->id,
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);

    expect($sale->seller_id)->toBe($this->seller->id);
});

it('el administrador elige el vendedor, y por defecto es él mismo', function () {
    $mine = app(CreateSale::class)->handle($this->admin, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);
    $chosen = app(CreateSale::class)->handle($this->admin, [
        'seller_id' => $this->seller->id,
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);

    expect($mine->seller_id)->toBe($this->admin->id)
        ->and($chosen->seller_id)->toBe($this->seller->id);
});

it('no se puede vender a nombre del superadmin ni de un usuario inactivo', function () {
    $super = User::factory()->superadmin()->create();
    $inactive = User::factory()->seller()->inactive()->create();

    foreach ([$super, $inactive] as $seller) {
        expect(fn () => app(CreateSale::class)->handle($this->admin, [
            'seller_id' => $seller->id,
            'items' => [item($this->variant)],
            'payments' => [pay($this->cash)],
        ]))->toThrow(ValidationException::class, 'vendedor');
    }
});

it('el cliente es opcional (consumidor final) y, si se indica, tiene que estar activo', function () {
    $anonymous = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);
    expect($anonymous->customer_id)->toBeNull();

    $customer = Customer::factory()->create();
    $named = app(CreateSale::class)->handle($this->seller, [
        'customer_id' => $customer->id,
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);
    expect($named->customer_id)->toBe($customer->id)
        ->and($named->payments->first()->customer_id)->toBe($customer->id);

    $inactive = Customer::factory()->inactive()->create();
    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'customer_id' => $inactive->id,
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]))->toThrow(ValidationException::class, 'cliente');
});

it('numera las ventas y los cobros con el id', function () {
    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);

    expect($sale->number())->toBe('V-'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT))
        ->and($sale->payments->first()->number())->toBe('C-'.str_pad((string) $sale->payments->first()->id, 5, '0', STR_PAD_LEFT));

    $first = Sale::factory()->make(['id' => 1]);
    $big = Sale::factory()->make(['id' => 12345]);
    expect($first->number())->toBe('V-00001')
        ->and($big->number())->toBe('V-12345')
        ->and(Payment::factory()->make(['id' => 7])->number())->toBe('C-00007');
});

it('es atómica: si falla a mitad de camino no queda nada', function () {
    SaleItem::creating(fn () => throw new RuntimeException('falla simulada'));

    expect(fn () => app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]))->toThrow(RuntimeException::class);

    expect(Sale::count())->toBe(0)
        ->and(SaleItem::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});

it('dispara SaleCreated y registra la actividad', function () {
    Event::fake([SaleCreated::class]);

    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [item($this->variant)],
        'payments' => [pay($this->cash)],
    ]);

    Event::assertDispatched(SaleCreated::class, fn (SaleCreated $event) => $event->sale->is($sale));

    $log = ActivityLog::where('event', ActivityEvent::Created)->where('subject_type', $sale->getMorphClass())->firstOrFail();
    expect($log->user_id)->toBe($this->seller->id)
        ->and($log->description)->toContain($sale->number());
});

it('no dispara SaleCreated cuando la venta falla', function () {
    Event::fake([SaleCreated::class]);

    try {
        app(CreateSale::class)->handle($this->seller, ['items' => [], 'payments' => []]);
    } catch (ValidationException) {
    }

    Event::assertNotDispatched(SaleCreated::class);
});

it('el total de la venta es siempre la suma de sus cobros', function () {
    $sale = app(CreateSale::class)->handle($this->admin, [
        'items' => [item($this->variant, 3)],
        'payments' => [pay($this->cash, '1000.00'), pay($this->card, '2000.00')],
    ]);

    $paid = $sale->payments->reduce(fn (string $carry, Payment $p) => bcadd($carry, $p->amount, 2), '0.00');

    expect($paid)->toBe($sale->total);
});
