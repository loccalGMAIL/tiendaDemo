<?php

use App\Actions\Accounts\CollectPayment;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\Reports\AccountBalancesReportPage;
use App\Filament\Pages\Reports\CollectionsReportPage;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Reports\AccountBalancesReport;
use App\Services\Reports\CollectionsReport;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Reports);
    Modules::enable(Module::CurrentAccount);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->ana = Customer::factory()->create(['name' => 'Ana Cliente', 'phone' => '341 555-0001', 'address' => 'Corrientes 123', 'city' => 'Rosario']);
    $this->beto = Customer::factory()->create(['name' => 'Beto Cliente', 'phone' => null, 'address' => null, 'city' => null]);
    $this->sinDeuda = Customer::factory()->create(['name' => 'Carla Sin Deuda']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;
    $this->method = PaymentMethod::factory()->create(['adjustment_percent' => '0.00']);
});

function reportAccountSale(Customer $customer, int $quantity, string $due): Sale
{
    $test = test();

    return app(CreateSale::class)->handle($test->admin, [
        'customer_id' => $customer->id,
        'date' => '2026-11-01',
        'due_date' => $due,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => $quantity]],
    ]);
}

/**
 * Ana: 3.000 vencidos el 10/11 (le cobran 1.000 → quedan 2.000), 2.000 el 20/11 y 500 hoy.
 * Beto: 4.000 el 15/12 y 1.000 el 30/6/2027. Carla no debe nada.
 */
function seedAccountReports(): void
{
    $test = test();

    reportAccountSale($test->ana, 3, '2026-11-10');
    reportAccountSale($test->ana, 2, '2026-11-20');
    reportAccountSale($test->beto, 4, '2026-12-15');
    reportAccountSale($test->beto, 1, '2027-06-30');

    $today = app(CreateSale::class)->handle($test->admin, [
        'customer_id' => $test->ana->id,
        'date' => '2026-11-16',
        'due_date' => '2026-11-16',
        'items' => [['variant_id' => $test->variant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $test->method->id, 'base_amount' => '500.00']],
    ]);

    expect($today->financed_amount)->toBe('500.00');

    app(CollectPayment::class)->handle($test->admin, [
        'customer_id' => $test->ana->id,
        'payment_method_id' => $test->method->id,
        'base_amount' => '1000.00',
        'date' => '2026-11-16',
    ]);
}

// --- Saldos pendientes por cliente --------------------------------------------------------------

it('arma una fila por cliente con deuda, con vencido, un mes por columna, más adelante y total', function () {
    seedAccountReports();

    $result = app(AccountBalancesReport::class)->run([]);

    expect($result['months'])->toBe(6)
        ->and($result['rows'])->toHaveCount(2)
        ->and(collect($result['rows'])->pluck('label')->all())->toBe(['Ana Cliente', 'Beto Cliente'])
        // Ana: vencido 2.000 (3.000 − 1.000 cobrados); este mes 2.000 + 500 (el que vence hoy no está vencido).
        ->and($result['rows'][0])->toMatchArray(['overdue' => '2000.00', 'm0' => '2500.00', 'm1' => '0.00', 'later' => '0.00', 'total' => '4500.00'])
        // Beto: 4.000 en diciembre y 1.000 que vence más adelante de los 6 meses.
        ->and($result['rows'][1])->toMatchArray(['overdue' => '0.00', 'm0' => '0.00', 'm1' => '4000.00', 'later' => '1000.00', 'total' => '5000.00'])
        ->and($result['totals'])->toMatchArray(['overdue' => '2000.00', 'm0' => '2500.00', 'm1' => '4000.00', 'later' => '1000.00', 'total' => '9500.00']);
});

it('las columnas llevan el mes actual y los siguientes según el selector', function () {
    seedAccountReports();
    $report = app(AccountBalancesReport::class);

    $six = $report->run([]);
    $labels = array_column($six['columns'], 'label');

    expect($labels)->toBe(['Cliente', 'Vencido', 'Nov 2026', 'Dic 2026', 'Ene 2027', 'Feb 2027', 'Mar 2027', 'Abr 2027', 'Más adelante', 'Total']);

    $twelve = $report->run(['months' => 12]);

    // Con 12 meses el vencimiento de junio de 2027 entra en su columna (7 meses desde noviembre).
    expect($twelve['months'])->toBe(12)
        ->and(count($twelve['columns']))->toBe(16)
        ->and($twelve['rows'][1]['m7'])->toBe('1000.00')
        ->and($twelve['rows'][1]['later'])->toBe('0.00')
        ->and($twelve['totals']['total'])->toBe('9500.00');

    // Un valor fuera de 6/12/18/24 vuelve a 6.
    expect($report->run(['months' => 7])['months'])->toBe(6);
});

it('no cuenta los vencimientos pagados ni los de ventas anuladas', function () {
    seedAccountReports();

    $cancelled = reportAccountSale($this->beto, 10, '2026-12-01');
    app(CancelSale::class)->handle($cancelled, $this->admin, 'Se devolvió');

    // Ana paga todo lo que debe.
    app(CollectPayment::class)->handle($this->admin, [
        'customer_id' => $this->ana->id,
        'payment_method_id' => $this->method->id,
        'base_amount' => '4500.00',
    ]);

    $result = app(AccountBalancesReport::class)->run([]);

    expect(collect($result['rows'])->pluck('label')->all())->toBe(['Beto Cliente'])
        ->and($result['totals']['total'])->toBe('5000.00');
});

it('sin deuda el informe queda vacío', function () {
    $result = app(AccountBalancesReport::class)->run([]);

    expect($result['rows'])->toBe([])->and($result['totals']['total'])->toBe('0.00');
});

// --- Cobranza del mes ---------------------------------------------------------------------------

it('lista los vencimientos del mes con los datos para cobrar, del más viejo al más nuevo', function () {
    seedAccountReports();

    $result = app(CollectionsReport::class)->run([]);

    expect($result['rows'])->toHaveCount(3)
        ->and(array_column($result['rows'], 'due'))->toBe(['10/11/2026', '16/11/2026', '20/11/2026'])
        ->and(array_column($result['rows'], 'balance'))->toBe(['2000.00', '500.00', '2000.00'])
        ->and(array_column($result['rows'], 'days'))->toBe(['6', '0', '0'])
        ->and($result['rows'][0])->toMatchArray(['label' => 'Ana Cliente', 'phone' => '341 555-0001', 'address' => 'Corrientes 123, Rosario'])
        ->and($result['totals']['balance'])->toBe('4500.00');
});

it('un cliente sin teléfono ni dirección se muestra con guion', function () {
    seedAccountReports();

    $result = app(CollectionsReport::class)->run(['month' => '2026-12']);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0])->toMatchArray(['label' => 'Beto Cliente', 'phone' => '—', 'address' => '—', 'days' => '0', 'balance' => '4000.00']);
});

it('puede incluir los atrasados de meses anteriores', function () {
    seedAccountReports();
    $report = app(CollectionsReport::class);

    $result = $report->run(['month' => '2026-12', 'include_overdue' => '1']);

    // Los tres de noviembre ya pasaron más el de diciembre.
    expect($result['rows'])->toHaveCount(4)
        ->and($result['totals']['balance'])->toBe('8500.00')
        ->and($report->run(['month' => '2026-12', 'include_overdue' => '0'])['rows'])->toHaveCount(1)
        // Un mes inválido vuelve al actual.
        ->and($report->run(['month' => 'basura'])['rows'])->toHaveCount(3);
});

// --- Pantallas y módulos --------------------------------------------------------------------------

it('con los dos módulos el administrador ve los informes y el vendedor no', function (string $path) {
    $this->actingAs($this->admin)->get($this->panel.$path)->assertOk();
    $this->actingAs($this->seller)->get($this->panel.$path)->assertForbidden();
})->with(['/informe-saldos', '/informe-cobranza']);

it('sin Informes o sin Cuenta corriente los informes no existen', function (Module $off) {
    Modules::disable($off);

    $this->actingAs($this->admin)->get($this->panel.'/informe-saldos')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informe-cobranza')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informes/saldos/imprimir')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/informes/cobranza/imprimir')->assertNotFound();

    expect(AccountBalancesReportPage::canAccess())->toBeFalse()
        ->and(CollectionsReportPage::shouldRegisterNavigation())->toBeFalse();
})->with([Module::Reports, Module::CurrentAccount]);

it('la pantalla de saldos muestra los datos y cambia con los meses', function () {
    seedAccountReports();
    $this->actingAs($this->admin);

    Livewire::test(AccountBalancesReportPage::class)
        ->assertSee('Ana Cliente')
        ->assertSee('Beto Cliente')
        ->assertSee('9.500,00')
        ->assertSee('Más adelante')
        // Este informe no se filtra por Desde y Hasta.
        ->assertDontSee('Desde')
        ->set('months', 12)
        ->assertSee('Jun 2027');
});

it('la pantalla de cobranza muestra los vencimientos y cambia con el mes y los atrasados', function () {
    seedAccountReports();
    $this->actingAs($this->admin);

    Livewire::test(CollectionsReportPage::class)
        ->assertSee('341 555-0001')
        ->assertSee('Corrientes 123, Rosario')
        ->assertSee('4.500,00')
        ->set('month', '2026-12')
        ->assertSee('Beto Cliente')
        ->assertDontSee('341 555-0001')
        ->set('includeOverdue', true)
        ->assertSee('341 555-0001');
});

it('el enlace de impresión lleva los filtros y la impresión los respeta', function () {
    seedAccountReports();
    $this->actingAs($this->admin);

    $url = Livewire::test(CollectionsReportPage::class)
        ->set('month', '2026-12')
        ->instance()
        ->printUrl();

    expect($url)->toContain('/informes/cobranza/imprimir')->and($url)->toContain('month=2026-12');

    $this->get($this->panel.'/informes/cobranza/imprimir?month=2026-12')
        ->assertOk()
        ->assertSee('Cobranza del mes')
        ->assertSee('Vencimientos de diciembre de 2026')
        ->assertSee('Beto Cliente')
        ->assertDontSee('Ana Cliente');

    $this->get($this->panel.'/informes/saldos/imprimir?months=12')
        ->assertOk()
        ->assertSee('Saldos pendientes por cliente')
        ->assertSee('próximos 12 meses')
        ->assertSee('Jun 2027');

    $this->actingAs($this->seller)->get($this->panel.'/informes/saldos/imprimir')->assertForbidden();
});
