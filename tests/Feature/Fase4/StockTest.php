<?php

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Stock\AdjustStock;
use App\Actions\Stock\CountStock;
use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Events\SaleCancelled;
use App\Facades\Modules;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::Stock);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    PaymentMethod::factory()->create();
});

function stockOf(ProductVariant $variant): int
{
    return $variant->fresh()->stock;
}

// --- Ajuste manual --------------------------------------------------------

it('un ajuste deja la cantidad indicada y registra el movimiento con saldo, motivo y usuario', function () {
    $movement = app(AdjustStock::class)->handle($this->variant, 8, 'Stock inicial', $this->admin);

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(8)
        ->and($movement->balance)->toBe(8)
        ->and($movement->reason)->toBe('Stock inicial')
        ->and($movement->user_id)->toBe($this->admin->id)
        ->and($movement->product_variant_id)->toBe($this->variant->id)
        ->and($this->variant->stock)->toBe(8)
        ->and(stockOf($this->variant))->toBe(8);

    $down = app(AdjustStock::class)->handle($this->variant, 5, 'Recuento', $this->admin);

    expect($down->quantity)->toBe(-3)->and($down->balance)->toBe(5);
});

it('un ajuste que no cambia nada no genera movimiento', function () {
    app(AdjustStock::class)->handle($this->variant, 4, 'Stock inicial', $this->admin);

    $result = app(AdjustStock::class)->handle($this->variant, 4, 'Recuento', $this->admin);

    expect($result)->toBeNull()
        ->and(StockMovement::count())->toBe(1);
});

it('el ajuste exige motivo, cantidad no negativa, administrador y módulo encendido', function () {
    expect(fn () => app(AdjustStock::class)->handle($this->variant, 3, '   ', $this->admin))
        ->toThrow(ValidationException::class, 'motivo');

    expect(fn () => app(AdjustStock::class)->handle($this->variant, -1, 'Recuento', $this->admin))
        ->toThrow(ValidationException::class, 'negativa');

    expect(fn () => app(AdjustStock::class)->handle($this->variant, 3, 'Recuento', $this->seller))
        ->toThrow(AuthorizationException::class);

    Modules::disable(Module::Stock);

    expect(fn () => app(AdjustStock::class)->handle($this->variant, 3, 'Recuento', $this->admin))
        ->toThrow(ValidationException::class, 'apagado');

    expect(StockMovement::count())->toBe(0)->and(stockOf($this->variant))->toBe(0);
});

// --- Carga por grilla -----------------------------------------------------

it('la carga por grilla genera un ajuste por cada diferencia y ninguno si no cambió', function () {
    $other = Product::factory()->create();
    $third = Product::factory()->create();
    $a = $this->variant;
    $b = $other->implicitVariant;
    $c = $third->implicitVariant;

    $result = app(CountStock::class)->handle([$a->id => '10', $b->id => 5, $c->id => ''], 'Stock inicial', $this->admin);

    expect($result)->toBe(['adjustments' => 2, 'minimums' => 0])
        ->and(stockOf($a))->toBe(10)
        ->and(stockOf($b))->toBe(5)
        ->and(stockOf($c))->toBe(0)
        ->and(StockMovement::count())->toBe(2)
        ->and(StockMovement::pluck('reason')->unique()->all())->toBe(['Stock inicial']);

    // El mismo recuento otra vez no cambia nada.
    $again = app(CountStock::class)->handle([$a->id => 10, $b->id => '5'], 'Recuento', $this->admin);

    expect($again)->toBe(['adjustments' => 0, 'minimums' => 0])
        ->and(StockMovement::count())->toBe(2);
});

it('la carga por grilla también actualiza el stock mínimo', function () {
    $result = app(CountStock::class)->handle([$this->variant->id => 10], 'Stock inicial', $this->admin, [$this->variant->id => '3']);

    expect($result)->toBe(['adjustments' => 1, 'minimums' => 1])
        ->and($this->variant->fresh()->min_stock)->toBe(3);

    $onlyMin = app(CountStock::class)->handle([], 'Recuento', $this->admin, [$this->variant->id => 4]);

    expect($onlyMin)->toBe(['adjustments' => 0, 'minimums' => 1])
        ->and($this->variant->fresh()->min_stock)->toBe(4)
        ->and(StockMovement::count())->toBe(1);
});

it('la carga por grilla es atómica: si un valor es inválido no se guarda nada', function () {
    $other = Product::factory()->create()->implicitVariant;

    expect(fn () => app(CountStock::class)->handle([$this->variant->id => 7, $other->id => '-3'], 'Recuento', $this->admin))
        ->toThrow(ValidationException::class);

    expect(stockOf($this->variant))->toBe(0)->and(StockMovement::count())->toBe(0);

    expect(fn () => app(CountStock::class)->handle([$this->variant->id => 'abc'], 'Recuento', $this->admin))
        ->toThrow(ValidationException::class, 'entero');

    expect(fn () => app(CountStock::class)->handle([999999 => 1], 'Recuento', $this->admin))
        ->toThrow(ValidationException::class, 'no existe');
});

it('la carga por grilla exige motivo, administrador y módulo encendido', function () {
    expect(fn () => app(CountStock::class)->handle([$this->variant->id => 1], '', $this->admin))
        ->toThrow(ValidationException::class, 'motivo');

    expect(fn () => app(CountStock::class)->handle([$this->variant->id => 1], 'Recuento', $this->seller))
        ->toThrow(AuthorizationException::class);

    Modules::disable(Module::Stock);

    expect(fn () => app(CountStock::class)->handle([$this->variant->id => 1], 'Recuento', $this->admin))
        ->toThrow(ValidationException::class, 'apagado');
});

// --- Historial ------------------------------------------------------------

it('el historial de stock es inmutable', function () {
    $movement = app(AdjustStock::class)->handle($this->variant, 5, 'Stock inicial', $this->admin);

    expect(Schema::hasColumn('stock_movements', 'updated_at'))->toBeFalse();
    expect(fn () => $movement->update(['reason' => 'otro']))->toThrow(LogicException::class);
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
    expect(StockMovement::find($movement->id)->reason)->toBe('Stock inicial');
});

it('el stock del producto es por variante y cada una tiene su historial', function () {
    $named = $this->product->variants()->create(['name' => 'M', 'position' => 1]);

    app(AdjustStock::class)->handle($this->variant, 3, 'Stock inicial', $this->admin);
    app(AdjustStock::class)->handle($named, 9, 'Stock inicial', $this->admin);

    expect(stockOf($this->variant))->toBe(3)
        ->and(stockOf($named))->toBe(9)
        ->and($this->variant->stockMovements)->toHaveCount(1)
        ->and($named->stockMovements)->toHaveCount(1);
});

it('el stock no se asigna en masa: solo cambia por movimientos', function () {
    // La fábrica ignora la protección de asignación; el modelo real la respeta.
    $variant = ProductVariant::create(['product_id' => $this->product->id, 'name' => 'L', 'stock' => 99]);

    expect($variant->fresh()->stock)->toBe(0);
});

it('los cambios de stock no llenan la actividad, pero sí los demás datos de la variante', function () {
    $named = $this->product->variants()->create(['name' => 'M', 'position' => 1]);
    $this->actingAs($this->admin);

    $created = ActivityLog::where('event', ActivityEvent::Created)->where('subject_type', $named->getMorphClass())->where('subject_id', $named->id)->first();
    expect($created)->not->toBeNull()->and($created->description)->toBe('Alta de variante: Remera — M');

    app(AdjustStock::class)->handle($named, 5, 'Stock inicial', $this->admin);
    sellVariant($this->admin, $named, 2);

    expect(ActivityLog::where('event', ActivityEvent::Updated)->where('subject_id', $named->id)->where('subject_type', $named->getMorphClass())->count())->toBe(0);

    $named->update(['price' => '1500.00']);

    $update = ActivityLog::where('event', ActivityEvent::Updated)->where('subject_id', $named->id)->where('subject_type', $named->getMorphClass())->firstOrFail();
    expect($update->properties['new'])->toBe(['price' => '1500.00']);
});

it('la variante implícita no se registra en la actividad', function () {
    $product = Product::factory()->create();

    expect(ActivityLog::where('subject_type', $product->implicitVariant->getMorphClass())
        ->where('subject_id', $product->implicitVariant->id)->count())->toBe(0);
});

// --- Venta y anulación ----------------------------------------------------

it('la venta descuenta el stock y registra el movimiento con su saldo y referencia', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);

    $sale = sellVariant($this->seller, $this->variant, 3);

    $movement = StockMovement::where('type', StockMovementType::Sale->value)->firstOrFail();

    expect(stockOf($this->variant))->toBe(7)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->balance)->toBe(7)
        ->and($movement->user_id)->toBe($this->seller->id)
        ->and($movement->reference->is($sale))->toBeTrue();
});

it('varias líneas de la misma variante se descuentan juntas', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $method = PaymentMethod::first();

    app(CreateSale::class)->handle($this->admin, [
        'items' => [
            ['variant_id' => $this->variant->id, 'quantity' => 2],
            ['variant_id' => $this->variant->id, 'quantity' => 3],
        ],
        'payments' => [['payment_method_id' => $method->id]],
    ]);

    expect(stockOf($this->variant))->toBe(5)
        ->and(StockMovement::where('type', StockMovementType::Sale->value)->count())->toBe(1)
        ->and(StockMovement::where('type', StockMovementType::Sale->value)->first()->quantity)->toBe(-5);
});

it('cada variante descuenta su propio stock', function () {
    $other = Product::factory()->create()->implicitVariant;
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    app(AdjustStock::class)->handle($other, 10, 'Stock inicial', $this->admin);

    sellVariant($this->admin, $this->variant, 4);

    expect(stockOf($this->variant))->toBe(6)->and(stockOf($other))->toBe(10);
});

it('anular la venta devuelve el stock con un movimiento de anulación', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $sale = sellVariant($this->seller, $this->variant, 3);

    app(CancelSale::class)->handle($sale, $this->admin, 'Error de carga');

    $movement = StockMovement::where('type', StockMovementType::Cancellation->value)->firstOrFail();

    expect(stockOf($this->variant))->toBe(10)
        ->and($movement->quantity)->toBe(3)
        ->and($movement->balance)->toBe(10)
        ->and($movement->user_id)->toBe($this->admin->id)
        ->and($movement->reference->is($sale))->toBeTrue();
});

it('por defecto avisa y deja vender sin stock: queda en negativo', function () {
    app(AdjustStock::class)->handle($this->variant, 2, 'Stock inicial', $this->admin);

    $sale = sellVariant($this->seller, $this->variant, 5);

    expect($sale->exists)->toBeTrue()
        ->and(stockOf($this->variant))->toBe(-3);

    app(CancelSale::class)->handle($sale, $this->admin, 'Devolución');

    expect(stockOf($this->variant))->toBe(2);
});

// --- Modo bloquear --------------------------------------------------------

describe('con el ajuste bloquear', function () {
    beforeEach(function () {
        Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block']);
        app(AdjustStock::class)->handle($this->variant, 2, 'Stock inicial', $this->admin);
    });

    it('rechaza la venta sin stock y no deja nada a medias', function () {
        expect(fn () => sellVariant($this->seller, $this->variant, 3))
            ->toThrow(ValidationException::class, 'No hay stock suficiente de «Remera» (disponible: 2, pedido: 3)');

        expect(Sale::count())->toBe(0)
            ->and(Payment::count())->toBe(0)
            ->and(StockMovement::count())->toBe(1)
            ->and(stockOf($this->variant))->toBe(2);
    });

    it('permite vender justo lo que hay', function () {
        sellVariant($this->seller, $this->variant, 2);

        expect(stockOf($this->variant))->toBe(0)->and(Sale::count())->toBe(1);
    });

    it('suma las líneas de la misma variante antes de comparar', function () {
        $method = PaymentMethod::first();

        expect(fn () => app(CreateSale::class)->handle($this->admin, [
            'items' => [
                ['variant_id' => $this->variant->id, 'quantity' => 2],
                ['variant_id' => $this->variant->id, 'quantity' => 1],
            ],
            'payments' => [['payment_method_id' => $method->id]],
        ]))->toThrow(ValidationException::class, 'pedido: 3');

        expect(Sale::count())->toBe(0);
    });

    it('si una variante no alcanza rechaza la venta entera y no toca las demás', function () {
        $plenty = Product::factory()->create()->implicitVariant;
        app(AdjustStock::class)->handle($plenty, 50, 'Stock inicial', $this->admin);
        $method = PaymentMethod::first();

        expect(fn () => app(CreateSale::class)->handle($this->admin, [
            'items' => [
                ['variant_id' => $plenty->id, 'quantity' => 10],
                ['variant_id' => $this->variant->id, 'quantity' => 5],
            ],
            'payments' => [['payment_method_id' => $method->id]],
        ]))->toThrow(ValidationException::class);

        expect(stockOf($plenty))->toBe(50)->and(stockOf($this->variant))->toBe(2)->and(Sale::count())->toBe(0);
    });
});

// --- Módulo apagado -------------------------------------------------------

it('con el módulo apagado la venta no toca el stock ni crea movimientos', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    Modules::disable(Module::Stock);

    sellVariant($this->seller, $this->variant, 3);

    expect(stockOf($this->variant))->toBe(10)->and(StockMovement::count())->toBe(1);
});

it('con el módulo apagado el ajuste bloquear no rechaza ventas', function () {
    Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block']);
    Modules::disable(Module::Stock);

    $sale = sellVariant($this->seller, $this->variant, 5);

    expect($sale->exists)->toBeTrue();
});

it('anular una venta hecha con Stock apagado no suma stock', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    Modules::disable(Module::Stock);
    $sale = sellVariant($this->seller, $this->variant, 3);

    Modules::enable(Module::Stock);
    app(CancelSale::class)->handle($sale, $this->admin, 'Error');

    expect(stockOf($this->variant))->toBe(10)
        ->and(StockMovement::where('type', StockMovementType::Cancellation->value)->count())->toBe(0);
});

it('anular una venta hecha con Stock encendido revierte el stock aunque el módulo se haya apagado', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $sale = sellVariant($this->seller, $this->variant, 3);

    Modules::disable(Module::Stock);
    app(CancelSale::class)->handle($sale, $this->admin, 'Error');

    expect(stockOf($this->variant))->toBe(10);
});

it('si el evento de anulación falla, la anulación completa se revierte', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $sale = sellVariant($this->seller, $this->variant, 3);

    Event::listen(SaleCancelled::class, fn () => throw new RuntimeException('falla simulada'));

    expect(fn () => app(CancelSale::class)->handle($sale, $this->admin, 'Error'))->toThrow(RuntimeException::class);

    expect($sale->fresh()->isCancelled())->toBeFalse()
        ->and($sale->payments()->whereNotNull('cancelled_at')->count())->toBe(0);
});
