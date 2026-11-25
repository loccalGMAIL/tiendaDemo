<?php

use App\Enums\Module;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Filament\Pages\PointOfSale;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::PointOfSale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo(now()->setDate(2026, 11, 24));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->remera = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa', 'sku' => 'REM-1']);
    $this->gorra = Product::factory()->priced('2500.00', '1000.00')->create(['name' => 'Gorra roja', 'sku' => 'GOR-1']);

    // Efectivo es el primero: es el que la ventana de cobro elige por defecto.
    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00', 'position' => 1]);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00', 'position' => 2]);
});

function caja(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? test()->seller)->test(PointOfSale::class);
}

it('muestra la caja con el ticket y la ventana de cobro abiertos', function () {
    caja()
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->assertOk()
        ->assertSee('Remera lisa')
        ->assertSee('Paga con')
        ->assertSee('Efectivo (−10 %)');
});

it('lleva al vendedor directo a la caja al ingresar y al administrador al escritorio', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => $this->seller->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect(PointOfSale::getUrl());

    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['email' => $this->admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());
});

it('sin el módulo Caja la pantalla no existe y el vendedor entra al escritorio', function () {
    Modules::disable(Module::PointOfSale);

    $this->actingAs($this->seller)->get('http://'.config('domains.panel').'/caja')->assertNotFound();

    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['email' => $this->seller->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());
});

it('agrega por código con el precio del catálogo y suma una unidad si se repite', function () {
    $component = caja()
        ->call('submitEntry', 'REM-1')
        ->call('submitEntry', ' REM-1 ');

    expect($component->get('lines'))->toBe([['variant_id' => $this->remera->implicitVariant->id, 'quantity' => 2]])
        ->and($component->instance()->ticketTotal())->toBe('2000.00');
});

it('suma la cantidad pedida con el multiplicador', function () {
    $component = caja()->call('submitEntry', '3*GOR-1');

    expect($component->get('lines'))->toBe([['variant_id' => $this->gorra->implicitVariant->id, 'quantity' => 3]]);
});

it('agrega directo cuando la búsqueda por nombre encuentra un solo producto', function () {
    $component = caja()->call('submitEntry', 'gorra');

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('results'))->toBe([]);
});

it('con varios resultados los muestra y agrega el elegido con la cantidad pedida', function () {
    Product::factory()->priced('1200.00')->create(['name' => 'Remera estampada', 'sku' => 'REM-2']);

    $component = caja()->call('submitEntry', '2*remera');

    expect($component->get('results'))->toHaveCount(2)
        ->and($component->get('lines'))->toBe([])
        ->and($component->get('resultsQuantity'))->toBe(2);

    $component->call('addVariant', $component->get('results')[1]['id'], $component->get('resultsQuantity'));

    expect($component->get('lines'))->toHaveCount(1)
        ->and($component->get('lines')[0]['quantity'])->toBe(2)
        ->and($component->get('results'))->toBe([]);
});

it('avisa cuando no encuentra nada y no toca el ticket', function () {
    caja()
        ->call('submitEntry', 'NO-EXISTE')
        ->assertNotified('No se encontró ningún producto para «NO-EXISTE».')
        ->assertSet('lines', []);
});

it('no agrega un producto inactivo aunque se pida por id', function () {
    $inactive = Product::factory()->inactive()->create();

    caja()
        ->call('addVariant', $inactive->implicitVariant->id)
        ->assertNotified('Ese producto no está disponible para la venta.')
        ->assertSet('lines', []);
});

it('cambia cantidades, quita la línea al bajar de uno y la quita con Supr', function () {
    $component = caja()
        ->call('submitEntry', 'REM-1')
        ->call('submitEntry', 'GOR-1')
        ->call('changeQuantity', 0, 1)
        ->call('setQuantity', 1, '5');

    expect(array_column($component->get('lines'), 'quantity'))->toBe([2, 5]);

    $component->call('changeQuantity', 0, -1)->call('changeQuantity', 0, -1);

    expect($component->get('lines'))->toBe([['variant_id' => $this->gorra->implicitVariant->id, 'quantity' => 5]]);

    $component->call('removeLine', 0);

    expect($component->get('lines'))->toBe([])
        ->and($component->get('selectedLine'))->toBeNull();
});

it('no abre el cobro con el ticket vacío', function () {
    caja()
        ->call('openCheckout')
        ->assertSet('checkingOut', false)
        ->assertNotified('Agregá al menos un producto para cobrar.');
});

it('cobra en efectivo con el descuento del medio, informa el vuelto y deja el ticket listo para otra venta', function () {
    $component = caja()
        ->call('submitEntry', '2*REM-1')
        ->call('openCheckout')
        ->set('tendered', '2.000')
        ->call('confirm')
        // 2 × 1000 = 2000; efectivo −10 % → 1800; paga con 2000 → vuelto 200.
        ->assertNotified('Venta V-00001 confirmada');

    $sale = Sale::query()->with('payments')->sole();

    expect($sale->channel)->toBe(SaleChannel::Counter)
        ->and($sale->seller_id)->toBe($this->seller->id)
        ->and($sale->customer_id)->toBeNull()
        ->and($sale->date->toDateString())->toBe('2026-11-24')
        ->and($sale->total)->toBe('1800.00')
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->payments->sole()->payment_method_id)->toBe($this->cash->id);

    expect($component->get('lines'))->toBe([])
        ->and($component->get('checkingOut'))->toBeFalse()
        ->and($component->get('lastSale'))->toMatchArray(['number' => 'V-00001', 'total' => '1800.00', 'change' => '200.00']);
});

it('no confirma si lo que entrega el cliente no alcanza', function () {
    caja()
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->set('tendered', '500')
        ->call('confirm')
        ->assertNotified('Lo que entrega no alcanza')
        ->assertSet('checkingOut', true);

    expect(Sale::query()->count())->toBe(0);
});

it('divide el pago: el último medio cubre lo que falta con su propio recargo', function () {
    caja()
        ->call('submitEntry', 'GOR-1')
        ->call('openCheckout')
        ->call('addPaymentLine')
        ->set('payments.0.base', '1.000')
        ->call('choosePaymentMethod', $this->card->id)
        ->call('confirm');

    $sale = Sale::query()->with('payments')->sole();

    // Efectivo cubre 1000 (−10 % → 900); la tarjeta el resto, 1500 (+15 % → 1725).
    expect($sale->total)->toBe('2625.00')
        ->and($sale->payments->pluck('amount', 'payment_method_id')->all())
        ->toBe([$this->cash->id => '900.00', $this->card->id => '1725.00']);
});

it('con el pago dividido exige el monto de cada medio salvo el último', function () {
    caja()
        ->call('submitEntry', 'GOR-1')
        ->call('openCheckout')
        ->call('addPaymentLine')
        ->call('choosePaymentMethod', $this->card->id)
        ->call('confirm')
        ->assertNotified('No se pudo confirmar la venta');

    expect(Sale::query()->count())->toBe(0);
});

it('cobra en la caja aunque el administrador cargue una venta online en el formulario', function () {
    caja($this->admin)
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->call('choosePaymentMethod', $this->card->id)
        ->call('confirm');

    expect(Sale::query()->sole())
        ->channel->toBe(SaleChannel::Counter)
        ->seller_id->toBe($this->admin->id)
        ->total->toBe('1150.00');
});

it('con Cuenta corriente deja el saldo a cuenta del cliente elegido', function () {
    Modules::enable(Module::CurrentAccount);
    $customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    $component = caja()
        ->call('submitEntry', 'GOR-1')
        ->call('openCheckout')
        ->set('onAccount', true)
        ->set('payments.0.base', '500')
        ->set('dueDate', '2026-12-24')
        ->call('confirm')
        ->assertNotified('No se pudo confirmar la venta');

    expect(Sale::query()->count())->toBe(0);

    $component
        ->callAction('selectCustomer', ['customer_id' => $customer->id])
        ->call('confirm');

    $sale = Sale::query()->sole();

    // Entrega 500 en efectivo (−10 % → 450); quedan 2000 a cuenta.
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->status)->toBe(SaleStatus::Active)
        ->and($sale->down_payment)->toBe('450.00')
        ->and($sale->financed_amount)->toBe('2000.00')
        ->and($sale->first_due_date->toDateString())->toBe('2026-12-24');
});

it('sin Cuenta corriente no se puede dejar saldo a cuenta', function () {
    caja()
        ->call('submitEntry', 'GOR-1')
        ->call('openCheckout')
        ->set('onAccount', true)
        ->assertSet('onAccount', false);
});

it('vaciar el ticket descarta productos y cliente sin vender', function () {
    $customer = Customer::factory()->create();

    caja()
        ->call('submitEntry', 'REM-1')
        ->callAction('selectCustomer', ['customer_id' => $customer->id])
        ->assertSet('customerId', $customer->id)
        ->call('clearTicket')
        ->assertSet('lines', [])
        ->assertSet('customerId', null);

    expect(Sale::query()->count())->toBe(0);
});
