<?php

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\PointOfSale;
use App\Filament\Pages\TicketSettings;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleTicket;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo(now()->setDate(2026, 11, 25)->setTime(10, 30));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create(['name' => 'Ana Vendedora']);

    $this->remera = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa', 'sku' => 'REM-1']);
    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00', 'position' => 1]);

    app(SiteContent::class)->save([...app(SiteContent::class)->all(), 'name' => 'Casa Lila', 'address' => 'Av. Siempreviva 742', 'phone' => '351 555-1234']);

    $this->panel = 'http://'.config('domains.panel');
});

/**
 * Venta de dos remeras en efectivo (2 × 1000 − 10 % = 1800) hecha por el vendedor.
 */
function ticketSale(): Sale
{
    $test = test();

    return app(CreateSale::class)->handle($test->seller, [
        'items' => [['variant_id' => $test->remera->implicitVariant->id, 'quantity' => 2]],
        'payments' => [['payment_method_id' => $test->cash->id]],
    ]);
}

it('imprime el ticket con el negocio, los productos, los totales y el cobro', function () {
    $sale = ticketSale();

    $this->actingAs($this->seller)
        ->get($this->panel."/ventas/{$sale->id}/ticket")
        ->assertOk()
        ->assertSeeInOrder([
            'Casa Lila', 'Av. Siempreviva 742', 'Tel. 351 555-1234',
            'Venta V-00001', '25/11/2026 10:30', 'Vendedor: Ana Vendedora', 'Cliente: Consumidor final',
            'Remera lisa', '2 × $ 1.000,00', '$ 2.000,00',
            'Subtotal', 'Descuentos', '$ -200,00', 'TOTAL', '$ 1.800,00',
            'Efectivo', '$ 1.800,00',
            'Gracias por su compra', 'No válido como factura',
        ])
        ->assertSee('size: 80mm auto', false)
        ->assertDontSee('window.print());', false)
        ->assertDontSee('ANULADA');
});

it('muestra paga con y el vuelto solo si lo entregado alcanza', function () {
    $sale = ticketSale();

    $this->actingAs($this->seller)
        ->get($this->panel."/ventas/{$sale->id}/ticket?pago=2.000")
        ->assertSeeInOrder(['Paga con', '$ 2.000,00', 'Vuelto', '$ 200,00']);

    $this->actingAs($this->seller)
        ->get($this->panel."/ventas/{$sale->id}/ticket?pago=1000")
        ->assertDontSee('Vuelto');
});

it('con imprimir abre el diálogo de impresión solo', function () {
    $sale = ticketSale();

    $this->actingAs($this->seller)
        ->get($this->panel."/ventas/{$sale->id}/ticket?imprimir=1")
        ->assertSee('window.print());', false);
});

it('respeta el formato configurado: ancho, encabezado, pie, datos y copias', function () {
    app(SaleTicket::class)->save([
        'paper_width' => 58,
        'show_site_contact' => false,
        'header' => "CUIT 20-12345678-9\n\nIVA Responsable Inscripto",
        'footer' => 'Cambios dentro de los 30 días',
        'show_seller' => false,
        'show_customer' => false,
        'show_codes' => true,
        'not_invoice_legend' => false,
        'copies' => 2,
    ]);
    $sale = ticketSale();

    $response = $this->actingAs($this->admin)->get($this->panel."/ventas/{$sale->id}/ticket");

    $response->assertOk()
        ->assertSee('size: 58mm auto', false)
        ->assertSeeInOrder(['CUIT 20-12345678-9', 'IVA Responsable Inscripto', 'REM-1', 'Cambios dentro de los 30 días', 'Copia 1 de 2', 'Copia 2 de 2'])
        ->assertDontSee('Av. Siempreviva 742')
        ->assertDontSee('Vendedor:')
        ->assertDontSee('Cliente:')
        ->assertDontSee('No válido como factura');

    expect(substr_count($response->getContent(), 'class="ticket"'))->toBe(2);
});

it('marca la venta anulada', function () {
    $sale = ticketSale();
    app(CancelSale::class)->handle($sale, $this->admin, 'Error de carga');

    $this->actingAs($this->admin)
        ->get($this->panel."/ventas/{$sale->id}/ticket")
        ->assertOk()
        ->assertSee('ANULADA');
});

it('con saldo a cuenta muestra lo que queda y el vencimiento', function () {
    Modules::enable(Module::CurrentAccount);
    $customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    $sale = app(CreateSale::class)->handle($this->seller, [
        'customer_id' => $customer->id,
        'due_date' => '2026-12-25',
        'items' => [['variant_id' => $this->remera->implicitVariant->id, 'quantity' => 2]],
        'payments' => [['payment_method_id' => $this->cash->id, 'base_amount' => '500']],
    ]);

    $this->actingAs($this->seller)
        ->get($this->panel."/ventas/{$sale->id}/ticket")
        ->assertSeeInOrder(['Cliente: Marta Cliente', 'Saldo a cuenta', '$ 1.500,00', 'Vence 25/12/2026', '$ 1.500,00']);
});

it('el vendedor no ve el ticket de otro vendedor y un usuario desactivado tampoco', function () {
    $sale = ticketSale();
    $other = User::factory()->seller()->create();
    $inactive = User::factory()->admin()->inactive()->create();

    $this->actingAs($other)->get($this->panel."/ventas/{$sale->id}/ticket")->assertForbidden();
    $this->actingAs($inactive)->get($this->panel."/ventas/{$sale->id}/ticket")->assertForbidden();
});

it('sin sesión el ticket manda al login', function () {
    $sale = ticketSale();

    $this->get($this->panel."/ventas/{$sale->id}/ticket")->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('la vista de la venta ofrece reimprimir el ticket', function () {
    $sale = ticketSale();

    Livewire::actingAs($this->seller)
        ->test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->assertActionHasUrl('ticket', route('sales.ticket', $sale))
        ->assertActionShouldOpenUrlInNewTab('ticket');
});

it('el administrador guarda el formato del ticket y queda en la actividad', function () {
    Livewire::actingAs($this->admin)
        ->test(TicketSettings::class)
        ->assertSchemaStateSet(['paper_width' => 80, 'print_on_confirm' => 'ask', 'copies' => 1])
        ->fillForm(['paper_width' => 58, 'footer' => 'Gracias, vuelva pronto', 'print_on_confirm' => 'always'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Ticket guardado');

    expect(app(SaleTicket::class)->settings())->toMatchArray([
        'paper_width' => 58,
        'footer' => 'Gracias, vuelva pronto',
        'print_on_confirm' => 'always',
        'show_seller' => true,
    ])->and(ActivityLog::query()->where('description', 'Cambio en el formato del ticket')->count())->toBe(1);
});

it('descarta valores fuera de lo permitido al guardar el formato', function () {
    $settings = app(SaleTicket::class)->save(['paper_width' => 100, 'copies' => 9, 'print_on_confirm' => 'nunca', 'footer' => str_repeat('a', 600)]);

    expect($settings)->toMatchArray(['paper_width' => 80, 'copies' => 3, 'print_on_confirm' => 'ask'])
        ->and(mb_strlen($settings['footer']))->toBe(SaleTicket::MAX_TEXT);
});

it('la caja imprime sola, pregunta o no hace nada según el formato', function (string $mode, ?string $event) {
    app(SaleTicket::class)->save(['print_on_confirm' => $mode]);

    $component = Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->set('tendered', '1000')
        ->call('confirm');

    $sale = Sale::query()->sole();
    $ticketUrl = route('sales.ticket', ['sale' => $sale, 'pago' => '1000.00']);

    expect($component->get('lastSale'))->toMatchArray(['ticket_url' => $ticketUrl, 'ask_print' => $mode === 'ask']);

    match ($event) {
        'pos-print-ticket' => $component->assertDispatched('pos-print-ticket', url: $ticketUrl)->assertNotDispatched('pos-ask-ticket'),
        'pos-ask-ticket' => $component->assertDispatched('pos-ask-ticket')->assertNotDispatched('pos-print-ticket'),
        default => $component->assertNotDispatched('pos-print-ticket')->assertNotDispatched('pos-ask-ticket'),
    };
})->with([
    'siempre' => ['always', 'pos-print-ticket'],
    'preguntar' => ['ask', 'pos-ask-ticket'],
    'nunca' => ['never', null],
]);
