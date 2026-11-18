<?php

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Stock\AdjustStock;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::Purchases);

    $this->admin = User::factory()->admin()->create();
    $this->supplier = Supplier::factory()->create();

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;
});

function stockPurchase(int $quantity = 10, string $number = 'F-1'): Purchase
{
    $test = test();

    return app(CreatePurchase::class)->handle($test->admin, [
        'supplier_id' => $test->supplier->id,
        'invoice_number' => $number,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => $quantity, 'unit_cost' => '650.00']],
    ]);
}

it('con Stock encendido la factura suma a la variante y deja su movimiento', function () {
    Modules::enable(Module::Stock);

    $purchase = stockPurchase(10);

    expect($this->variant->refresh()->stock)->toBe(10);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and($movement->quantity)->toBe(10)
        ->and($movement->balance)->toBe(10)
        ->and($movement->reference->is($purchase))->toBeTrue()
        ->and($movement->user_id)->toBe($this->admin->id)
        ->and($movement->reason)->toContain('F-1');

    stockPurchase(5, 'F-2');

    expect($this->variant->refresh()->stock)->toBe(15);
});

it('con Stock apagado la factura no toca las existencias', function () {
    stockPurchase(10);

    expect($this->variant->refresh()->stock)->toBe(0)->and(StockMovement::count())->toBe(0);
});

it('anular la factura quita lo que sumó, con su movimiento de anulación', function () {
    Modules::enable(Module::Stock);

    $purchase = stockPurchase(10);
    stockPurchase(3, 'F-2');

    app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga');

    expect($this->variant->refresh()->stock)->toBe(3);

    $movement = StockMovement::query()->where('type', StockMovementType::Cancellation->value)->firstOrFail();

    expect($movement->quantity)->toBe(-10)
        ->and($movement->balance)->toBe(3)
        ->and($movement->reference->is($purchase))->toBeTrue();
});

it('no se anula una factura si dejaría el stock negativo y no queda nada a medias', function () {
    Modules::enable(Module::Stock);

    $purchase = stockPurchase(10);

    // Se vendió o ajustó: quedan 4 y la factura había sumado 10.
    app(AdjustStock::class)->handle($this->variant->refresh(), 4, 'Recuento', $this->admin);

    expect(fn () => app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga'))
        ->toThrow(ValidationException::class, 'stock negativo');

    expect($purchase->refresh()->isCancelled())->toBeFalse()
        ->and($this->variant->refresh()->stock)->toBe(4)
        ->and(StockMovement::query()->where('type', StockMovementType::Cancellation->value)->count())->toBe(0);
});

it('anular revierte lo que sumó aunque Stock se haya apagado después', function () {
    Modules::enable(Module::Stock);

    $purchase = stockPurchase(10);

    Modules::disable(Module::Stock);

    app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga');

    expect($this->variant->refresh()->stock)->toBe(0);
});

it('anular una factura cargada con Stock apagado no resta nada', function () {
    $purchase = stockPurchase(10);

    Modules::enable(Module::Stock);

    app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga');

    expect($this->variant->refresh()->stock)->toBe(0)->and(StockMovement::count())->toBe(0);
});
