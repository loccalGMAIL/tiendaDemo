<?php

use App\Actions\CashRegister\CloseCashSession;
use App\Actions\CashRegister\OpenCashSession;
use App\Actions\Sales\CreateSale as CreateSaleAction;
use App\Enums\CashMovementType;
use App\Enums\Module;
use App\Enums\SaleChannel;
use App\Facades\Modules;
use App\Filament\Pages\PointOfSale;
use App\Filament\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Resources\CashSessions\Pages\ViewCashSession;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\CashRegisterSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::PointOfSale);
    Modules::enable(Module::CashControl);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create(['name' => 'Ana']);
    $this->register = CashRegister::factory()->create(['name' => 'Caja 1']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['sku' => 'REM-1']);
    $this->cash = PaymentMethod::factory()->cash()->create(['name' => 'Efectivo', 'position' => 1]);

    $this->panel = 'http://'.config('domains.panel');
});

it('con una sola caja la elige sola y pide abrirla antes de vender', function () {
    Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->assertSet('cashRegisterId', $this->register->id)
        ->assertSee('Caja 1 está cerrada')
        ->assertDontSee('Escaneá un código o buscá un producto para empezar.')
        ->callAction('openSession', ['opening_amount' => '5000'])
        ->assertNotified('Caja 1 abierta')
        ->assertSee('Turno T-00001')
        ->assertSee('Escaneá un código o buscá un producto para empezar.');

    expect(CashSession::query()->sole())->opening_amount->toBe('5000.00')->opened_by->toBe($this->seller->id);
});

it('con varias cajas hay que elegir en cuál se trabaja', function () {
    $other = CashRegister::factory()->create(['name' => 'Caja 2']);

    Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->assertSet('cashRegisterId', null)
        ->assertSee('¿En qué caja trabajás?')
        ->call('chooseRegister', $other->id)
        ->assertSet('cashRegisterId', $other->id)
        ->assertSee('Caja 2 está cerrada')
        ->call('changeRegister')
        ->assertSet('cashRegisterId', null);
});

it('vende en el turno abierto de la caja y lo cobrado en efectivo entra al cajón', function () {
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '5000');

    Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->call('confirm')
        ->assertNotified('Venta V-00001 confirmada');

    expect(Sale::query()->sole()->cash_session_id)->toBe($session->id)
        ->and(CashMovement::query()->where('type', CashMovementType::Sale)->sole()->amount)->toBe('1000.00');
});

it('si cierran la caja mientras se cobra, la venta no se confirma', function () {
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '0');

    $component = Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout');

    app(CloseCashSession::class)->handle($this->admin, $session, ['counted_amount' => '0']);

    $component->call('confirm')->assertNotified('La caja está cerrada');

    expect(Sale::query()->count())->toBe(0);
});

it('registra ingresos desde la caja y el retiro solo aparece si está permitido', function () {
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '1000');

    $component = Livewire::actingAs($this->seller)->test(PointOfSale::class);

    $component->mountAction('cashMovement')
        ->assertMountedActionModalSee('Ingreso: se agrega efectivo')
        ->assertMountedActionModalDontSee('Retiro: se saca efectivo')
        ->fillForm(['type' => 'deposit', 'amount' => '500', 'reason' => 'Cambio'])
        ->callMountedAction()
        ->assertNotified('Ingreso de $ 500,00 registrado');

    expect(CashMovement::query()->where('cash_session_id', $session->id)->where('type', CashMovementType::Deposit)->sole()->amount)->toBe('500.00');
});

it('cierra la caja desde la pantalla y con arqueo ciego el vendedor no ve lo esperado', function () {
    Modules::updateSettings(Module::CashControl, ['blind_count' => true]);
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '1000');

    $component = Livewire::actingAs($this->seller)->test(PointOfSale::class);

    $component->mountAction('closeSession')
        ->assertMountedActionModalDontSee('Efectivo esperado en el cajón')
        ->assertMountedActionModalSee('Contá el efectivo del cajón sin mirar el sistema')
        ->fillForm(['counted_amount' => '900', 'left_amount' => '500'])
        ->callMountedAction()
        ->assertNotified('Caja 1 cerrada')
        ->assertSee('Caja 1 está cerrada');

    expect($session->fresh())->difference->toBe('-100.00')->left_amount->toBe('500.00');
});

it('no cierra la caja con un ticket sin cobrar', function () {
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '0');

    Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->call('submitEntry', 'REM-1')
        ->callAction('closeSession', ['counted_amount' => '0'])
        ->assertNotified('Hay un ticket sin cobrar');

    expect($session->fresh()->isOpen())->toBeTrue();
});

it('sin el módulo la caja vende sin turnos', function () {
    Modules::disable(Module::CashControl);

    Livewire::actingAs($this->seller)
        ->test(PointOfSale::class)
        ->assertDontSee('está cerrada')
        ->call('submitEntry', 'REM-1')
        ->call('openCheckout')
        ->call('confirm');

    expect(Sale::query()->sole()->cash_session_id)->toBeNull();
});

it('«Nueva venta» de mostrador puede entrar a la caja abierta', function () {
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '0');
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->assertFormFieldIsHidden('cash_session_id')
        ->fillForm(['channel' => SaleChannel::Counter->value])
        ->assertFormFieldIsVisible('cash_session_id')
        ->assertSchemaStateSet(['cash_session_id' => $session->id])
        ->fillForm([
            'items' => [['variant_id' => $this->product->implicitVariant->id, 'quantity' => 1, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Sale::query()->sole()->cash_session_id)->toBe($session->id);
});

it('al anular se elige de qué caja sale el efectivo', function () {
    $own = app(OpenCashSession::class)->handle($this->seller, $this->register, '0');
    $sale = app(CreateSaleAction::class)->handle($this->seller, [
        'cash_session_id' => $own->id,
        'items' => [['variant_id' => $this->product->implicitVariant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $this->cash->id]],
    ]);
    $other = app(OpenCashSession::class)->handle($this->admin, CashRegister::factory()->create(['name' => 'Caja 2']), '1000');

    Livewire::actingAs($this->admin)
        ->test(ViewSale::class, ['record' => $sale->getRouteKey()])
        ->callAction('cancel', ['reason' => 'Devolución', 'refund_cash_session_id' => $other->id])
        ->assertNotified('Venta anulada');

    expect(CashMovement::query()->where('type', CashMovementType::Refund)->sole()->cash_session_id)->toBe($other->id);
});

it('el administrador ve los turnos y cierra uno que quedó abierto', function () {
    $open = app(OpenCashSession::class)->handle($this->seller, $this->register, '2000');
    $closed = CashSession::factory()->closed('1000.00', '1000.00')->create();

    Livewire::actingAs($this->admin)
        ->test(ListCashSessions::class)
        ->assertCanSeeTableRecords([$open, $closed]);

    Livewire::actingAs($this->admin)
        ->test(ViewCashSession::class, ['record' => $open->getRouteKey()])
        ->assertSee('Esperado ahora')
        ->assertSee('$ 2.000,00')
        ->callAction('close', ['counted_amount' => '2000'])
        ->assertNotified('Turno cerrado');

    expect($open->fresh())->isOpen()->toBeFalse()->difference->toBe('0.00');
});

it('el resumen del turno lo imprime su vendedor, sin lo esperado si el arqueo es ciego', function () {
    Modules::updateSettings(Module::CashControl, ['blind_count' => true]);
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '1500');
    app(CloseCashSession::class)->handle($this->seller, $session, ['counted_amount' => '1400']);
    $url = $this->panel."/caja/turnos/{$session->id}/imprimir";

    $this->actingAs($this->seller)->get($url)
        ->assertOk()
        ->assertSeeInOrder(['Cierre de caja', 'Caja 1', 'Fondo inicial', '$ 1.500,00', 'Contado', '$ 1.400,00'])
        ->assertDontSee('Esperado')
        ->assertDontSee('Faltan');

    $this->actingAs($this->admin)->get($url)->assertOk()->assertSeeInOrder(['Esperado', '$ 1.500,00', 'Diferencia', 'Faltan $ 100,00']);

    $this->actingAs(User::factory()->seller()->create())->get($url)->assertForbidden();
});

it('el ticket usa el rollo de la caja si tiene uno propio', function () {
    $this->register->update(['paper_width' => 58]);
    $session = app(OpenCashSession::class)->handle($this->seller, $this->register, '0');
    $sale = app(CreateSaleAction::class)->handle($this->seller, [
        'cash_session_id' => $session->id,
        'items' => [['variant_id' => $this->product->implicitVariant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $this->cash->id]],
    ]);

    $this->actingAs($this->seller)->get($this->panel."/ventas/{$sale->id}/ticket")->assertSee('size: 58mm auto', false);
});

it('la instalación arranca con una caja y no la repite', function () {
    CashRegister::query()->delete();

    $this->seed(CashRegisterSeeder::class);
    $this->seed(CashRegisterSeeder::class);

    expect(CashRegister::query()->pluck('name')->all())->toBe(['Caja 1']);
});
