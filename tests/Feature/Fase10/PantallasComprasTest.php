<?php

use App\Actions\Purchases\CreatePurchase as CreatePurchaseAction;
use App\Actions\Purchases\CreatePurchaseOrder as CreatePurchaseOrderAction;
use App\Actions\Purchases\IssuePurchaseOrder;
use App\Enums\Module;
use App\Enums\PurchaseOrderStatus;
use App\Facades\Modules;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Resources\Purchases\Pages\ViewPurchase;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Widgets\LatestPurchases;
use App\Filament\Widgets\PurchasesOverview;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Purchases);

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->supplier = Supplier::factory()->create(['name' => 'Distribuidora Sur']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;
});

function screenOrder(): PurchaseOrder
{
    $test = test();

    return app(CreatePurchaseOrderAction::class)->handle($test->admin, [
        'supplier_id' => $test->supplier->id,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 10]],
    ]);
}

// --- Acceso -------------------------------------------------------------------------

it('el administrador ve las pantallas de Compras y el vendedor no', function (string $path) {
    $this->actingAs($this->admin)->get($this->panel.$path)->assertOk();
    $this->actingAs($this->seller)->get($this->panel.$path)->assertForbidden();
})->with(['/suppliers', '/purchase-orders', '/purchases', '/purchase-orders/create', '/purchases/create']);

it('con Compras apagado las pantallas no existen', function (string $path) {
    Modules::disable(Module::Purchases);

    $this->actingAs($this->admin)->get($this->panel.$path)->assertNotFound();
})->with(['/suppliers', '/purchase-orders', '/purchases', '/purchase-orders/create', '/purchases/create']);

// --- Proveedores ---------------------------------------------------------------------

it('lista proveedores y deja crear uno', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListSuppliers::class)->assertCanSeeTableRecords([$this->supplier]);

    $this->get($this->panel.'/suppliers/create')->assertOk()->assertSee('CUIT');
});

// --- Órdenes de compra ------------------------------------------------------------------

it('crea una orden de compra en borrador desde el formulario', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 6]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = PurchaseOrder::query()->firstOrFail();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(6);
});

it('lista las órdenes y muestra su vista con las acciones según el estado', function () {
    $order = screenOrder();
    $this->actingAs($this->admin);

    Livewire::test(ListPurchaseOrders::class)->assertCanSeeTableRecords([$order]);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getKey()])
        ->assertSee('OC-')
        ->assertActionVisible('issue')
        ->assertActionVisible('print')
        ->assertActionHidden('invoice')
        ->callAction('issue')
        ->assertNotified('Orden emitida')
        ->assertActionHidden('issue')
        ->assertActionVisible('invoice');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Issued);
});

it('anula una orden desde su vista con motivo', function () {
    $order = screenOrder();
    $this->actingAs($this->admin);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getKey()])
        ->callAction('cancel', ['reason' => 'El proveedor cerró'])
        ->assertNotified('Orden anulada');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($order->cancellation_reason)->toBe('El proveedor cerró');
});

it('una orden emitida ya no se edita', function () {
    $order = screenOrder();
    $this->actingAs($this->admin);

    $this->get($this->panel.'/purchase-orders/'.$order->id.'/edit')->assertOk();

    app(IssuePurchaseOrder::class)->handle($order, $this->admin);

    $this->get($this->panel.'/purchase-orders/'.$order->id.'/edit')->assertForbidden();
});

// --- Impresión de la orden ------------------------------------------------------------------

it('imprime la orden solo para el administrador', function () {
    $order = screenOrder();

    $this->actingAs($this->admin)
        ->get($this->panel.'/compras/ordenes/'.$order->id.'/imprimir')
        ->assertOk()
        ->assertSee($order->number())
        ->assertSee('Distribuidora Sur')
        ->assertSee('Remera')
        // La orden no lleva costos.
        ->assertDontSee('600,00');

    $this->actingAs($this->seller)->get($this->panel.'/compras/ordenes/'.$order->id.'/imprimir')->assertForbidden();
    $this->actingAs($this->admin)->get($this->panel.'/compras/ordenes/9999/imprimir')->assertNotFound();

    auth()->logout();
    $this->get($this->panel.'/compras/ordenes/'.$order->id.'/imprimir')->assertRedirect();

    Modules::disable(Module::Purchases);
    $this->actingAs($this->admin)->get($this->panel.'/compras/ordenes/'.$order->id.'/imprimir')->assertNotFound();
});

// --- Facturas de compra --------------------------------------------------------------------

it('carga una factura directa desde el formulario y actualiza el costo', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'A-0001-00000010',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4, 'unit_cost' => '650.00']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->total)->toBe('2600.00')
        ->and($purchase->invoice_number)->toBe('A-0001-00000010')
        ->and($this->product->refresh()->cost)->toBe('650.00');
});

it('carga la factura desde una orden emitida con lo pedido precargado', function () {
    $order = app(IssuePurchaseOrder::class)->handle(screenOrder(), $this->admin);
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['order' => $order->id])
        ->test(CreatePurchase::class)
        ->assertFormSet(['purchase_order_id' => $order->id, 'supplier_id' => $this->supplier->id])
        ->fillForm([
            'invoice_number' => 'F-77',
            'items' => [['variant_id' => $this->variant->id, 'ordered_quantity' => 10, 'quantity' => 8, 'unit_cost' => '640.00']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $purchase = Purchase::query()->firstOrFail();

    expect($purchase->purchase_order_id)->toBe($order->id)
        ->and($purchase->items->first()->quantity)->toBe(8)
        ->and($purchase->items->first()->ordered_quantity)->toBe(10)
        ->and($order->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('muestra el rechazo de una factura inválida sin crearla', function () {
    $this->actingAs($this->admin);

    app(CreatePurchaseAction::class)->handle($this->admin, [
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'F-1',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_cost' => '10']],
    ]);

    Livewire::test(CreatePurchase::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'F-1',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_cost' => '10']],
        ])
        ->call('create')
        ->assertNotified('No se pudo cargar la factura');

    expect(Purchase::count())->toBe(1);
});

it('lista facturas, las ve y las anula con motivo', function () {
    $purchase = app(CreatePurchaseAction::class)->handle($this->admin, [
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'F-5',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'unit_cost' => '650.00']],
    ]);
    $this->actingAs($this->admin);

    Livewire::test(ListPurchases::class)->assertCanSeeTableRecords([$purchase]);

    Livewire::test(ViewPurchase::class, ['record' => $purchase->getKey()])
        ->assertSee('F-5')
        ->assertActionVisible('cancel')
        ->callAction('cancel', ['reason' => 'Error de carga'])
        ->assertNotified('Factura anulada')
        ->assertActionHidden('cancel');

    expect($purchase->refresh()->isCancelled())->toBeTrue();
});

// --- Escritorio y productos ------------------------------------------------------------------

it('el escritorio muestra las compras del mes y las órdenes sin recibir', function () {
    app(IssuePurchaseOrder::class)->handle(screenOrder(), $this->admin);
    app(CreatePurchaseAction::class)->handle($this->admin, [
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'F-9',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'unit_cost' => '650.00']],
    ]);
    $this->actingAs($this->admin);

    Livewire::test(PurchasesOverview::class)->assertSee('1.300,00')->assertSee('Órdenes sin recibir');
    Livewire::test(LatestPurchases::class)->assertSee('F-9')->assertSee('Distribuidora Sur');

    expect(PurchasesOverview::canView())->toBeTrue();

    $this->actingAs($this->seller);
    expect(PurchasesOverview::canView())->toBeFalse()->and(LatestPurchases::canView())->toBeFalse();

    $this->actingAs($this->admin);
    Modules::disable(Module::Purchases);
    expect(PurchasesOverview::canView())->toBeFalse();
});

it('el producto ofrece el proveedor habitual solo con Compras encendido', function () {
    $this->actingAs($this->admin);

    $this->get($this->panel.'/products/'.$this->product->id.'/edit')->assertOk()->assertSee('Proveedor habitual');

    Modules::disable(Module::Purchases);

    $this->get($this->panel.'/products/'.$this->product->id.'/edit')->assertOk()->assertDontSee('Proveedor habitual');
});
