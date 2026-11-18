<?php

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\CreatePurchaseOrder;
use App\Actions\Purchases\IssuePurchaseOrder;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\Reports\PurchasesReportPage;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\PurchasesReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Reports);
    Modules::enable(Module::Purchases);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->sur = Supplier::factory()->create(['name' => 'Distribuidora Sur']);
    $this->norte = Supplier::factory()->create(['name' => 'Distribuidora Norte']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;
});

function reportPurchase(Supplier $supplier, string $number, string $date, int $quantity, string $cost): Purchase
{
    $test = test();

    return app(CreatePurchase::class)->handle($test->admin, [
        'supplier_id' => $supplier->id,
        'invoice_number' => $number,
        'date' => $date,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => $quantity, 'unit_cost' => $cost]],
    ]);
}

/**
 * Sur: dos facturas del mes (6.500 y 1.000); Norte: una (1.000); una anulada del mes
 * y una de octubre de Norte (500).
 */
function seedPurchasesReport(): void
{
    $test = test();

    reportPurchase($test->sur, 'F-1', '2026-11-05', 10, '650.00');
    reportPurchase($test->norte, 'F-3', '2026-11-08', 4, '250.00');
    reportPurchase($test->sur, 'F-2', '2026-11-10', 2, '500.00');

    $cancelled = reportPurchase($test->sur, 'F-4', '2026-11-12', 3, '900.00');
    app(CancelPurchase::class)->handle($cancelled, $test->admin, 'Error de carga');

    reportPurchase($test->norte, 'F-5', '2026-10-20', 1, '500.00');
}

// --- Servicio ----------------------------------------------------------------------------

it('agrupa las compras del mes por proveedor sin las facturas anuladas', function () {
    seedPurchasesReport();

    $result = app(PurchasesReport::class)->run([]);

    expect($result['group'])->toBe('supplier')
        ->and($result['rows'])->toHaveCount(2)
        // Ordenado por nombre: Norte antes que Sur.
        ->and($result['rows'][0])->toMatchArray(['label' => 'Distribuidora Norte', 'count' => '1', 'total' => '1000.00', 'average' => '1000.00'])
        ->and($result['rows'][1])->toMatchArray(['label' => 'Distribuidora Sur', 'count' => '2', 'total' => '7500.00', 'average' => '3750.00'])
        ->and($result['totals']['count'])->toBe('3')
        ->and($result['totals']['total'])->toBe('8500.00')
        ->and($result['totals']['average'])->toBe('2833.33');
});

it('filtra por período y por proveedor', function () {
    seedPurchasesReport();
    $report = app(PurchasesReport::class);

    expect($report->run(['from' => '2026-10-01', 'to' => '2026-11-30'])['totals']['total'])->toBe('9000.00')
        ->and($report->run(['from' => '2026-11-06', 'to' => '2026-11-30'])['totals']['count'])->toBe('2')
        ->and($report->run(['supplier_id' => $this->sur->id])['rows'])->toHaveCount(1)
        ->and($report->run(['supplier_id' => $this->sur->id])['totals']['total'])->toBe('7500.00')
        // Fechas al revés se ordenan; inválidas vuelven al mes actual.
        ->and($report->run(['from' => '2026-11-30', 'to' => '2026-11-01'])['totals']['count'])->toBe('3')
        ->and($report->run(['from' => 'cualquier cosa'])['totals']['count'])->toBe('3');
});

it('el detalle lista una fila por factura con su orden', function () {
    seedPurchasesReport();

    $order = app(IssuePurchaseOrder::class)->handle(
        app(CreatePurchaseOrder::class)->handle($this->admin, [
            'supplier_id' => $this->norte->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 5]],
        ]),
        $this->admin,
    );
    app(CreatePurchase::class)->handle($this->admin, [
        'purchase_order_id' => $order->id,
        'invoice_number' => 'F-9',
        'date' => '2026-11-14',
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 5, 'unit_cost' => '100.00']],
    ]);

    $result = app(PurchasesReport::class)->run(['group' => 'detail']);

    expect($result['group'])->toBe('detail')
        ->and(collect($result['rows'])->pluck('label')->all())->toBe(['F-1', 'F-3', 'F-2', 'F-9'])
        ->and($result['rows'][0])->toMatchArray(['supplier' => 'Distribuidora Sur', 'order' => '—', 'total' => '6500.00', 'date' => '05/11/2026'])
        ->and($result['rows'][3]['order'])->toBe($order->number())
        ->and($result['totals']['total'])->toBe('9000.00');
});

it('sin facturas todo da cero y un grupo desconocido cae en proveedor', function () {
    $result = app(PurchasesReport::class)->run(['group' => 'inventado']);

    expect($result['group'])->toBe('supplier')
        ->and($result['rows'])->toBe([])
        ->and($result['totals']['total'])->toBe('0.00')
        ->and($result['totals']['average'])->toBe('0.00');
});

// --- Pantalla: pide Informes y Compras ---------------------------------------------------------

it('con los dos módulos el administrador ve el informe y el vendedor no', function () {
    seedPurchasesReport();

    $this->actingAs($this->admin)->get($this->panel.'/informe-compras')->assertOk()->assertSee('Compras por proveedor');
    $this->actingAs($this->seller)->get($this->panel.'/informe-compras')->assertForbidden();

    expect(PurchasesReportPage::canAccess())->toBeFalse();

    $this->actingAs($this->admin);

    expect(PurchasesReportPage::canAccess())->toBeTrue()
        ->and(PurchasesReportPage::shouldRegisterNavigation())->toBeTrue();
});

it('sin Informes o sin Compras el informe no existe', function (Module $off) {
    Modules::disable($off);

    $this->actingAs($this->admin)->get($this->panel.'/informe-compras')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informes/compras/imprimir')->assertNotFound();

    expect(PurchasesReportPage::canAccess())->toBeFalse()
        ->and(PurchasesReportPage::shouldRegisterNavigation())->toBeFalse();
})->with([Module::Reports, Module::Purchases]);

it('la pantalla muestra los datos y cambia con los filtros', function () {
    seedPurchasesReport();
    $this->actingAs($this->admin);

    Livewire::test(PurchasesReportPage::class)
        ->assertSee('Distribuidora Sur')
        ->assertSee('Distribuidora Norte')
        ->assertSee('8.500,00')
        // El nombre de Sur sigue en el desplegable: se comprueba su importe.
        ->assertSee('7.500,00')
        ->set('supplierId', $this->norte->id)
        ->assertSee('1.000,00')
        ->assertDontSee('7.500,00')
        ->set('supplierId', null)
        ->set('group', 'detail')
        ->assertSee('F-1')
        ->assertSee('F-3');
});

it('el enlace de impresión lleva los filtros de la pantalla', function () {
    $this->actingAs($this->admin);

    $url = Livewire::test(PurchasesReportPage::class)
        ->set('supplierId', $this->sur->id)
        ->set('group', 'detail')
        ->instance()
        ->printUrl();

    expect($url)->toContain('/informes/compras/imprimir')
        ->and($url)->toContain('supplier_id='.$this->sur->id)
        ->and($url)->toContain('group=detail');
});

// --- Impresión ------------------------------------------------------------------------------------

it('la impresión respeta los filtros y es solo del administrador', function () {
    seedPurchasesReport();

    $this->actingAs($this->admin)
        ->get($this->panel.'/informes/compras/imprimir?supplier_id='.$this->norte->id)
        ->assertOk()
        ->assertSee('Compras por proveedor')
        ->assertSee('Distribuidora Norte')
        ->assertDontSee('Distribuidora Sur');

    $this->actingAs($this->seller)->get($this->panel.'/informes/compras/imprimir')->assertForbidden();

    auth()->logout();
    $this->get($this->panel.'/informes/compras/imprimir')->assertRedirect();
});
