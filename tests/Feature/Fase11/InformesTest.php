<?php

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\Reports\PaymentsReportPage;
use App\Filament\Pages\Reports\SalesReportPage;
use App\Filament\Widgets\SalesOverview;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use App\Services\Reports\PaymentsReport;
use App\Services\Reports\SalesReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Reports);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
    $this->ana = User::factory()->seller()->create(['name' => 'Ana']);
    $this->luis = User::factory()->seller()->create(['name' => 'Luis']);

    $this->remera = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->gorra = Product::factory()->priced('500.00', '200.00')->create(['name' => 'Gorra']);

    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00']);

    $this->marta = Customer::factory()->create(['name' => 'Marta Cliente']);
    $this->pedro = Customer::factory()->create(['name' => 'Pedro Cliente']);
});

function reportSale(User $seller, string $date, Product $product, int $quantity, PaymentMethod $method, ?Customer $customer = null): Sale
{
    return app(CreateSale::class)->handle($seller, [
        'customer_id' => $customer?->id,
        'date' => $date,
        'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => $quantity]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);
}

/**
 * Dos ventas vigentes del mes, una anulada y una del mes anterior.
 */
function seedReportSales(): void
{
    $test = test();

    // Ana: 2 × 1000 = 2000, efectivo −10 % = −200 → 1800; costo 1200
    reportSale($test->ana, '2026-11-05', $test->remera, 2, $test->cash, $test->marta);
    // Luis: 1 × 500 = 500, tarjeta +15 % = +75 → 575; costo 200
    reportSale($test->luis, '2026-11-10', $test->gorra, 1, $test->card, $test->pedro);

    $cancelled = reportSale($test->ana, '2026-11-12', $test->remera, 1, $test->cash);
    app(CancelSale::class)->handle($cancelled, $test->admin, 'Error de carga');

    reportSale($test->ana, '2026-10-20', $test->remera, 1, $test->cash);
}

// --- Ventas y ganancia ----------------------------------------------------------

it('suma ventas, ajustes, costo y ganancia del mes sin las anuladas', function () {
    seedReportSales();

    $result = app(SalesReport::class)->run([]);

    expect($result['group'])->toBe('detail')
        ->and($result['rows'])->toHaveCount(2)
        ->and($result['totals']['subtotal'])->toBe('2500.00')
        ->and($result['totals']['adjustment'])->toBe('-125.00')
        ->and($result['totals']['total'])->toBe('2375.00')
        ->and($result['totals']['cost'])->toBe('1400.00')
        ->and($result['totals']['profit'])->toBe('1100.00')
        ->and($result['totals']['margin'])->toBe('44.00');
});

it('la ganancia no incluye el recargo ni el descuento del medio de pago', function () {
    seedReportSales();

    $rows = collect(app(SalesReport::class)->run([])['rows']);

    // Ana: ganancia sobre el subtotal (2000 − 1200), no sobre el total cobrado (1800)
    expect($rows->firstWhere('seller', 'Ana')['profit'])->toBe('800.00')
        ->and($rows->firstWhere('seller', 'Ana')['margin'])->toBe('40.00')
        ->and($rows->firstWhere('seller', 'Luis')['profit'])->toBe('300.00')
        ->and($rows->firstWhere('seller', 'Luis')['margin'])->toBe('60.00');
});

it('el detalle muestra cliente y consumidor final', function () {
    seedReportSales();

    $rows = collect(app(SalesReport::class)->run([])['rows']);

    expect($rows->pluck('customer')->all())->toBe(['Marta Cliente', 'Pedro Cliente']);

    reportSale($this->ana, '2026-11-11', $this->gorra, 1, $this->cash);

    expect(collect(app(SalesReport::class)->run([])['rows'])->pluck('customer'))->toContain('Consumidor final');
});

it('filtra por período y por vendedor', function () {
    seedReportSales();
    $report = app(SalesReport::class);

    expect($report->run(['from' => '2026-10-01', 'to' => '2026-11-30'])['rows'])->toHaveCount(3)
        ->and($report->run(['from' => '2026-11-06', 'to' => '2026-11-30'])['rows'])->toHaveCount(1)
        ->and($report->run(['seller_id' => $this->luis->id])['rows'])->toHaveCount(1)
        ->and($report->run(['seller_id' => $this->luis->id])['totals']['total'])->toBe('575.00')
        // fechas al revés se ordenan; fechas inválidas vuelven al mes actual
        ->and($report->run(['from' => '2026-11-30', 'to' => '2026-11-01'])['rows'])->toHaveCount(2)
        ->and($report->run(['from' => 'cualquier cosa'])['rows'])->toHaveCount(2);
});

it('agrupa por vendedor y por producto', function () {
    seedReportSales();
    $report = app(SalesReport::class);

    $bySeller = $report->run(['group' => 'seller']);

    expect($bySeller['rows'])->toHaveCount(2)
        ->and($bySeller['rows'][0]['label'])->toBe('Ana')
        ->and($bySeller['rows'][0]['count'])->toBe('1')
        ->and($bySeller['rows'][0]['total'])->toBe('1800.00')
        ->and($bySeller['totals']['total'])->toBe('2375.00');

    $byProduct = $report->run(['group' => 'product']);

    expect($byProduct['rows'])->toHaveCount(2)
        ->and($byProduct['rows'][0]['label'])->toBe('Gorra')
        ->and($byProduct['rows'][0]['profit'])->toBe('300.00')
        ->and($byProduct['rows'][1]['label'])->toBe('Remera')
        ->and($byProduct['rows'][1]['count'])->toBe('2')
        ->and($byProduct['totals']['subtotal'])->toBe('2500.00')
        ->and($byProduct['totals']['profit'])->toBe('1100.00');
});

it('un grupo desconocido cae en el detalle', function () {
    seedReportSales();

    expect(app(SalesReport::class)->run(['group' => 'inventado'])['group'])->toBe('detail');
});

it('el total de ventas del informe coincide con el del escritorio', function () {
    seedReportSales();

    $total = app(SalesReport::class)->run([])['totals']['total'];

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)->assertSee(Money::format($total));
});

it('sin ventas todo da cero', function () {
    $result = app(SalesReport::class)->run([]);

    expect($result['rows'])->toBe([])
        ->and($result['totals']['total'])->toBe('0.00')
        ->and($result['totals']['margin'])->toBe('0.00');
});

// --- Cobros realizados ----------------------------------------------------------

it('suma los cobros vigentes con subtotales por medio y por cobrador', function () {
    seedReportSales();

    $result = app(PaymentsReport::class)->run([]);

    // 1800 (efectivo, Ana) + 575 (tarjeta, Luis); el cobro de la venta anulada no cuenta
    expect($result['rows'])->toHaveCount(2)
        ->and($result['totals']['base'])->toBe('2500.00')
        ->and($result['totals']['adjustment'])->toBe('-125.00')
        ->and($result['totals']['amount'])->toBe('2375.00');

    $byMethod = collect($result['subtotals']['Por medio de pago']);
    $byCollector = collect($result['subtotals']['Por cobrador']);

    expect($byMethod->firstWhere('label', 'Efectivo')['amount'])->toBe('1800.00')
        ->and($byMethod->firstWhere('label', 'Tarjeta 3 cuotas')['amount'])->toBe('575.00')
        ->and($byCollector->firstWhere('label', 'Ana')['amount'])->toBe('1800.00')
        ->and($byCollector->firstWhere('label', 'Luis')['count'])->toBe('1');
});

it('filtra los cobros por período, medio y cobrador', function () {
    seedReportSales();
    $report = app(PaymentsReport::class);

    expect($report->run(['payment_method_id' => $this->card->id])['totals']['amount'])->toBe('575.00')
        ->and($report->run(['collected_by' => $this->ana->id])['rows'])->toHaveCount(1)
        ->and($report->run(['from' => '2026-10-01', 'to' => '2026-10-31'])['totals']['amount'])->toBe('900.00');
});

it('el total cobrado del informe coincide con el del escritorio', function () {
    seedReportSales();

    $total = app(PaymentsReport::class)->run([])['totals']['amount'];

    $this->actingAs($this->admin);

    Livewire::test(SalesOverview::class)->assertSee(Money::format($total));
});

// --- Pantallas ------------------------------------------------------------------

it('el administrador ve los informes y el vendedor no', function () {
    seedReportSales();

    $this->actingAs($this->admin)->get($this->panel.'/informe-ventas')->assertOk()->assertSee('Ventas y ganancia');
    $this->actingAs($this->admin)->get($this->panel.'/informe-cobros')->assertOk()->assertSee('Cobros realizados');
    $this->actingAs($this->ana)->get($this->panel.'/informe-ventas')->assertForbidden();
    $this->actingAs($this->ana)->get($this->panel.'/informe-cobros')->assertForbidden();
});

it('la pantalla muestra los datos y cambia con los filtros', function () {
    seedReportSales();
    $this->actingAs($this->admin);

    Livewire::test(SalesReportPage::class)
        ->assertSee('Marta Cliente')
        ->assertSee('Pedro Cliente')
        ->set('sellerId', $this->luis->id)
        ->assertSee('Pedro Cliente')
        ->assertDontSee('Marta Cliente')
        ->set('sellerId', null)
        ->set('group', 'product')
        ->assertSee('Remera')
        ->assertSee('Gorra');

    Livewire::test(PaymentsReportPage::class)
        ->assertSee('Efectivo')
        ->set('paymentMethodId', $this->card->id)
        ->assertSee('Tarjeta 3 cuotas')
        ->assertDontSee('1.800,00');
});

it('el enlace de impresión lleva los filtros de la pantalla', function () {
    $this->actingAs($this->admin);

    $url = Livewire::test(SalesReportPage::class)
        ->set('sellerId', $this->luis->id)
        ->set('group', 'seller')
        ->instance()
        ->printUrl();

    expect($url)->toContain('/informes/ventas/imprimir')
        ->and($url)->toContain('seller_id='.$this->luis->id)
        ->and($url)->toContain('group=seller')
        ->and($url)->toContain('from=2026-11-01');
});

// --- Impresión ------------------------------------------------------------------

it('la impresión respeta los filtros', function () {
    seedReportSales();

    $this->actingAs($this->admin)
        ->get($this->panel.'/informes/ventas/imprimir?seller_id='.$this->luis->id)
        ->assertOk()
        ->assertSee('Ventas y ganancia')
        ->assertSee('Pedro Cliente')
        ->assertDontSee('Marta Cliente');

    $this->actingAs($this->admin)
        ->get($this->panel.'/informes/cobros/imprimir?payment_method_id='.$this->card->id)
        ->assertOk()
        ->assertSee('Cobros realizados')
        ->assertSee('Tarjeta 3 cuotas')
        ->assertDontSee('1.800,00');
});

it('la impresión es solo del administrador y de informes que existen', function () {
    seedReportSales();

    $this->actingAs($this->ana)->get($this->panel.'/informes/ventas/imprimir')->assertForbidden();
    $this->actingAs($this->admin)->get($this->panel.'/informes/inexistente/imprimir')->assertNotFound();
    auth()->logout();
    $this->get($this->panel.'/informes/ventas/imprimir')->assertRedirect();
});

it('con Informes apagado las pantallas y la impresión no existen', function () {
    Modules::disable(Module::Reports);

    $this->actingAs($this->admin)->get($this->panel.'/informe-ventas')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informe-cobros')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informes/ventas/imprimir')->assertNotFound();
});
