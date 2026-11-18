<?php

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\CancelPurchaseOrder;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\CreatePurchaseOrder;
use App\Actions\Purchases\IssuePurchaseOrder;
use App\Actions\Purchases\UpdatePurchaseOrder;
use App\Actions\Sales\CreateSale;
use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Enums\PurchaseOrderStatus;
use App\Facades\Modules;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::Purchases);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->supplier = Supplier::factory()->create(['name' => 'Distribuidora Sur']);

    $this->remera = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->gorra = Product::factory()->priced('500.00', '200.00')->create(['name' => 'Gorra']);
    $this->remeraVariant = $this->remera->implicitVariant;
    $this->gorraVariant = $this->gorra->implicitVariant;
});

function buyLine(mixed $variant, int|string $quantity, string|int|float $cost = '100.00'): array
{
    return ['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost' => $cost];
}

function buyOrderLine(mixed $variant, int|string $quantity): array
{
    return ['variant_id' => $variant->id, 'quantity' => $quantity];
}

function draftOrder(?Supplier $supplier = null, ?array $items = null): PurchaseOrder
{
    $test = test();

    return app(CreatePurchaseOrder::class)->handle($test->admin, [
        'supplier_id' => ($supplier ?? $test->supplier)->id,
        'items' => $items ?? [buyOrderLine($test->remeraVariant, 10), buyOrderLine($test->gorraVariant, 5)],
    ]);
}

function issuedOrder(?Supplier $supplier = null, ?array $items = null): PurchaseOrder
{
    return app(IssuePurchaseOrder::class)->handle(draftOrder($supplier, $items), test()->admin);
}

function buyDirect(array $data = []): Purchase
{
    $test = test();

    return app(CreatePurchase::class)->handle($test->admin, [
        'supplier_id' => $test->supplier->id,
        'invoice_number' => 'A-0001-00000001',
        'items' => [buyLine($test->remeraVariant, 10, '650.00')],
        ...$data,
    ]);
}

// --- Órdenes de compra ----------------------------------------------------------

it('crea una orden en borrador con su numeración y sus ítems', function () {
    $order = draftOrder();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->number())->toBe('OC-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT))
        ->and($order->supplier_id)->toBe($this->supplier->id)
        ->and($order->user_id)->toBe($this->admin->id)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->first()->product_id)->toBe($this->remera->id)
        ->and($order->items->first()->quantity)->toBe(10)
        ->and($order->items->first()->received_quantity)->toBe(0);
});

it('suma las cantidades cuando una variante se repite en la orden', function () {
    $order = draftOrder(items: [buyOrderLine($this->remeraVariant, 3), buyOrderLine($this->remeraVariant, 4)]);

    expect($order->items)->toHaveCount(1)->and($order->items->first()->quantity)->toBe(7);
});

it('rechaza una orden sin ítems, con cantidades inválidas, sin proveedor o con proveedor inactivo', function (array $data, string $field) {
    $data = [
        'supplier_id' => $this->supplier->id,
        'items' => [buyOrderLine($this->remeraVariant, 1)],
        ...$data,
    ];

    expect(fn () => app(CreatePurchaseOrder::class)->handle($this->admin, $data))
        ->toThrow(ValidationException::class);

    try {
        app(CreatePurchaseOrder::class)->handle($this->admin, $data);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(PurchaseOrder::count())->toBe(0);
})->with([
    'sin ítems' => [['items' => []], 'items'],
    'cantidad cero' => [['items' => [['variant_id' => 1, 'quantity' => 0]]], 'items'],
    'cantidad con decimales' => [['items' => [['variant_id' => 1, 'quantity' => '1.5']]], 'items'],
    'producto inexistente' => [['items' => [['variant_id' => 9999, 'quantity' => 1]]], 'items'],
    'sin proveedor' => [['supplier_id' => null], 'supplier_id'],
]);

it('rechaza una orden de un proveedor inactivo', function () {
    $inactive = Supplier::factory()->inactive()->create();

    expect(fn () => draftOrder($inactive))->toThrow(ValidationException::class);
});

it('edita la orden solo mientras está en borrador', function () {
    $order = draftOrder();

    $updated = app(UpdatePurchaseOrder::class)->handle($order, $this->admin, [
        'supplier_id' => $this->supplier->id,
        'date' => '2026-11-10',
        'notes' => 'Urgente',
        'items' => [buyOrderLine($this->gorraVariant, 20)],
    ]);

    expect($updated->items)->toHaveCount(1)
        ->and($updated->items->first()->quantity)->toBe(20)
        ->and($updated->notes)->toBe('Urgente')
        ->and($updated->date->toDateString())->toBe('2026-11-10');

    app(IssuePurchaseOrder::class)->handle($order, $this->admin);

    expect(fn () => app(UpdatePurchaseOrder::class)->handle($order, $this->admin, [
        'supplier_id' => $this->supplier->id,
        'items' => [buyOrderLine($this->gorraVariant, 1)],
    ]))->toThrow(ValidationException::class, 'borrador');
});

it('emite una orden en borrador y no una segunda vez', function () {
    $order = draftOrder();

    $issued = app(IssuePurchaseOrder::class)->handle($order, $this->admin);

    expect($issued->status)->toBe(PurchaseOrderStatus::Issued)->and($issued->issued_at)->not->toBeNull();

    expect(fn () => app(IssuePurchaseOrder::class)->handle($order, $this->admin))->toThrow(ValidationException::class);
});

it('anula una orden con motivo obligatorio y una sola vez', function () {
    $order = issuedOrder();

    expect(fn () => app(CancelPurchaseOrder::class)->handle($order, $this->admin, '  '))
        ->toThrow(ValidationException::class, 'motivo');

    $cancelled = app(CancelPurchaseOrder::class)->handle($order, $this->admin, 'El proveedor no tiene stock');

    expect($cancelled->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($cancelled->cancelled_by)->toBe($this->admin->id)
        ->and($cancelled->cancellation_reason)->toBe('El proveedor no tiene stock')
        ->and($cancelled->cancelled_at)->not->toBeNull();

    expect(fn () => app(CancelPurchaseOrder::class)->handle($order, $this->admin, 'Otra vez'))
        ->toThrow(ValidationException::class, 'ya está anulada');
});

it('no anula una orden que tiene una factura vigente', function () {
    $order = issuedOrder();
    $purchase = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 10), buyLine($this->gorraVariant, 5)],
    ]);

    expect(fn () => app(CancelPurchaseOrder::class)->handle($order, $this->admin, 'Motivo'))
        ->toThrow(ValidationException::class, 'anulá primero la factura');

    app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga');

    expect(app(CancelPurchaseOrder::class)->handle($order->refresh(), $this->admin, 'Ahora sí')->status)
        ->toBe(PurchaseOrderStatus::Cancelled);
});

// --- Facturas de compra: directa --------------------------------------------------

it('carga una factura directa con sus ítems, subtotales y total', function () {
    $purchase = buyDirect([
        'date' => '2026-11-12',
        'notes' => 'Entrega en el local',
        'items' => [buyLine($this->remeraVariant, 10, '650.00'), buyLine($this->gorraVariant, 4, '210.50')],
    ]);

    expect($purchase->supplier_id)->toBe($this->supplier->id)
        ->and($purchase->purchase_order_id)->toBeNull()
        ->and($purchase->invoice_number)->toBe('A-0001-00000001')
        ->and($purchase->date->toDateString())->toBe('2026-11-12')
        ->and($purchase->total)->toBe('7342.00')
        ->and($purchase->user_id)->toBe($this->admin->id)
        ->and($purchase->items)->toHaveCount(2)
        ->and($purchase->items->first()->quantity)->toBe(10)
        ->and($purchase->items->first()->unit_cost)->toBe('650.00')
        ->and($purchase->items->first()->subtotal)->toBe('6500.00')
        ->and($purchase->items->first()->ordered_quantity)->toBeNull()
        ->and($purchase->items->last()->subtotal)->toBe('842.00');
});

it('rechaza una factura directa sin proveedor, sin número, sin ítems o con valores inválidos', function (array $data, string $field) {
    expect(fn () => buyDirect($data))->toThrow(ValidationException::class);

    try {
        buyDirect($data);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }

    expect(Purchase::count())->toBe(0);
})->with([
    'sin proveedor' => [['supplier_id' => null], 'supplier_id'],
    'sin número' => [['invoice_number' => '  '], 'invoice_number'],
    'sin ítems' => [['items' => []], 'items'],
    'cantidad cero' => [['items' => [['variant_id' => 1, 'quantity' => 0, 'unit_cost' => '10.00']]], 'items'],
    'costo negativo' => [['items' => [['variant_id' => 1, 'quantity' => 1, 'unit_cost' => '-5']]], 'items'],
    'costo no numérico' => [['items' => [['variant_id' => 1, 'quantity' => 1, 'unit_cost' => 'abc']]], 'items'],
]);

it('deja el costo del producto como el de la factura sin tocar las ventas ya hechas', function () {
    $method = PaymentMethod::factory()->create(['adjustment_percent' => '0.00']);

    $sale = app(CreateSale::class)->handle($this->seller, [
        'items' => [['variant_id' => $this->remeraVariant->id, 'quantity' => 2]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);

    expect($sale->items->first()->unit_cost)->toBe('600.00');

    buyDirect(['items' => [buyLine($this->remeraVariant, 10, '650.00')]]);

    expect($this->remera->refresh()->cost)->toBe('650.00')
        ->and($sale->items()->first()->unit_cost)->toBe('600.00')
        ->and($sale->refresh()->total_cost)->toBe('1200.00');

    // La última factura gana.
    buyDirect(['invoice_number' => 'A-0001-00000002', 'items' => [buyLine($this->remeraVariant, 5, '700.00')]]);

    expect($this->remera->refresh()->cost)->toBe('700.00');
});

// --- Facturas de compra: desde una orden ---------------------------------------------

it('carga la factura de una orden emitida: toma su proveedor, registra lo pedido y la deja recibida', function () {
    $other = Supplier::factory()->create();
    $order = issuedOrder();

    $purchase = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        // El proveedor de la orden manda aunque se indique otro.
        'supplier_id' => $other->id,
        'invoice_number' => 'F-100',
        'items' => [buyLine($this->remeraVariant, 8, '650.00'), buyLine($this->gorraVariant, 5, '210.00')],
    ]);

    expect($purchase->supplier_id)->toBe($this->supplier->id)
        ->and($purchase->purchase_order_id)->toBe($order->id)
        ->and($purchase->items->first()->ordered_quantity)->toBe(10)
        ->and($purchase->items->first()->quantity)->toBe(8)
        ->and($purchase->items->last()->ordered_quantity)->toBe(5);

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->items->first()->received_quantity)->toBe(8)
        ->and($order->items->last()->received_quantity)->toBe(5)
        ->and($order->purchase->is($purchase))->toBeTrue();
});

it('solo se carga la factura de una orden emitida', function () {
    $draft = draftOrder();

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $draft->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 1)],
    ]))->toThrow(ValidationException::class, 'orden emitida');

    $issued = issuedOrder();
    app(CancelPurchaseOrder::class)->handle($issued, $this->admin, 'Motivo');

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $issued->id,
        'invoice_number' => 'F-2',
        'items' => [buyLine($this->remeraVariant, 1)],
    ]))->toThrow(ValidationException::class, 'orden emitida');
});

it('no puede llegar más de lo pedido ni un producto que no estaba en la orden', function () {
    $order = issuedOrder(items: [buyOrderLine($this->remeraVariant, 10)]);

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 11)],
    ]))->toThrow(ValidationException::class, 'se pidieron 10');

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 5), buyLine($this->remeraVariant, 6)],
    ]))->toThrow(ValidationException::class, 'se pidieron 10');

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->gorraVariant, 1)],
    ]))->toThrow(ValidationException::class, 'no estaba en la orden');

    expect(Purchase::count())->toBe(0)->and($order->refresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

it('tiene que haber llegado algo, y una línea en cero es lo que no llegó', function () {
    $order = issuedOrder();

    expect(fn () => app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 0), buyLine($this->gorraVariant, 0)],
    ]))->toThrow(ValidationException::class, 'al menos un producto');

    $purchase = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-2',
        'items' => [buyLine($this->remeraVariant, 10, '650.00'), buyLine($this->gorraVariant, 0)],
    ]);

    expect($purchase->items)->toHaveCount(1)
        ->and($order->refresh()->items->last()->received_quantity)->toBe(0);
});

// --- Número de factura -----------------------------------------------------------------

it('no repite el número de factura de un proveedor entre las vigentes', function () {
    $first = buyDirect();

    expect(fn () => buyDirect())->toThrow(ValidationException::class, 'Ya hay una factura vigente');

    // Otro proveedor puede usar el mismo número.
    expect(buyDirect(['supplier_id' => Supplier::factory()->create()->id])->exists)->toBeTrue();

    // Si la anterior está anulada, se puede volver a cargar.
    app(CancelPurchase::class)->handle($first, $this->admin, 'Error de carga');

    expect(buyDirect()->exists)->toBeTrue();
});

// --- Anulación de facturas ---------------------------------------------------------------

it('anula una factura con motivo, reabre su orden y deja el costo como está', function () {
    $order = issuedOrder();
    $purchase = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 10, '650.00'), buyLine($this->gorraVariant, 5, '210.00')],
    ]);

    expect(fn () => app(CancelPurchase::class)->handle($purchase, $this->admin, ''))->toThrow(ValidationException::class, 'motivo');

    $cancelled = app(CancelPurchase::class)->handle($purchase, $this->admin, 'Factura equivocada');

    expect($cancelled->isCancelled())->toBeTrue()
        ->and($cancelled->cancelled_by)->toBe($this->admin->id)
        ->and($cancelled->cancellation_reason)->toBe('Factura equivocada');

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrderStatus::Issued)
        ->and($order->items->sum('received_quantity'))->toBe(0)
        ->and($order->purchase)->toBeNull()
        // El costo no se revierte.
        ->and($this->remera->refresh()->cost)->toBe('650.00');

    expect(fn () => app(CancelPurchase::class)->handle($purchase, $this->admin, 'Otra vez'))
        ->toThrow(ValidationException::class, 'ya está anulada');
});

it('una orden con la factura anulada admite una factura nueva', function () {
    $order = issuedOrder(items: [buyOrderLine($this->remeraVariant, 10)]);
    $first = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 10)],
    ]);
    app(CancelPurchase::class)->handle($first, $this->admin, 'Error');

    $second = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 10)],
    ]);

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->purchase->is($second))->toBeTrue();
});

// --- Permisos, módulo y actividad ----------------------------------------------------------

it('solo el administrador opera con compras', function () {
    expect(fn () => app(CreatePurchaseOrder::class)->handle($this->seller, [
        'supplier_id' => $this->supplier->id,
        'items' => [buyOrderLine($this->remeraVariant, 1)],
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => app(CreatePurchase::class)->handle($this->seller, [
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'F-1',
        'items' => [buyLine($this->remeraVariant, 1)],
    ]))->toThrow(AuthorizationException::class);

    $purchase = buyDirect();

    expect(fn () => app(CancelPurchase::class)->handle($purchase, $this->seller, 'Motivo'))
        ->toThrow(AuthorizationException::class);
});

it('con el módulo apagado no se puede operar', function () {
    Modules::disable(Module::Purchases);

    expect(fn () => draftOrder())->toThrow(ValidationException::class, 'apagado');
});

it('las policies siguen las reglas de cada estado', function () {
    $draft = draftOrder();
    $issued = issuedOrder();
    $purchase = buyDirect();

    expect($this->admin->can('update', $draft))->toBeTrue()
        ->and($this->admin->can('update', $issued))->toBeFalse()
        ->and($this->admin->can('issue', $draft))->toBeTrue()
        ->and($this->admin->can('issue', $issued))->toBeFalse()
        ->and($this->admin->can('cancel', $issued))->toBeTrue()
        ->and($this->admin->can('delete', $issued))->toBeFalse()
        ->and($this->admin->can('update', $purchase))->toBeFalse()
        ->and($this->admin->can('delete', $purchase))->toBeFalse()
        ->and($this->admin->can('cancel', $purchase))->toBeTrue()
        ->and($this->seller->can('viewAny', Supplier::class))->toBeFalse()
        ->and($this->seller->can('viewAny', PurchaseOrder::class))->toBeFalse()
        ->and($this->seller->can('viewAny', Purchase::class))->toBeFalse()
        // Un proveedor sin historial se borra; con órdenes o facturas, no.
        ->and($this->admin->can('delete', Supplier::factory()->create()))->toBeTrue()
        ->and($this->admin->can('delete', $this->supplier))->toBeFalse()
        ->and($this->seller->can('delete', Supplier::factory()->create()))->toBeFalse();
});

it('registra en la actividad las altas, la emisión y las anulaciones', function () {
    $order = issuedOrder();
    $purchase = app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-9',
        'items' => [buyLine($this->remeraVariant, 10)],
    ]);
    app(CancelPurchase::class)->handle($purchase, $this->admin, 'Error de carga');

    $descriptions = ActivityLog::query()->pluck('description');

    expect($descriptions->contains(fn (string $text) => str_starts_with($text, 'Alta de orden de compra')))->toBeTrue()
        ->and($descriptions->contains(fn (string $text) => str_starts_with($text, 'Emisión de orden de compra')))->toBeTrue()
        ->and($descriptions->contains(fn (string $text) => str_starts_with($text, 'Alta de factura de compra F-9')))->toBeTrue()
        ->and($descriptions->contains(fn (string $text) => str_starts_with($text, 'Anulación de factura de compra F-9: Error de carga')))->toBeTrue()
        ->and(ActivityLog::query()->where('event', ActivityEvent::Cancelled->value)->count())->toBe(1);
});
