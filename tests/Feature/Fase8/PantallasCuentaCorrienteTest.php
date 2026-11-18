<?php

use App\Actions\Accounts\CollectPayment;
use App\Actions\Sales\CreateSale as CreateSaleAction;
use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\AccountMovementsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PurchasedItemsRelationManager;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Widgets\AccountOverview;
use App\Filament\Widgets\CustomerAccountStats;
use App\Filament\Widgets\OverdueInstallments;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::CurrentAccount);
    $this->travelTo(now()->setDate(2026, 11, 16));

    $this->panel = 'http://'.config('domains.panel');
    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->otherSeller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create(['name' => 'Marta Cliente']);

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera']);
    $this->variant = $this->product->implicitVariant;

    $this->method = PaymentMethod::factory()->create(['name' => 'Transferencia', 'adjustment_percent' => '0.00']);
});

function screenAccountSale(string $due = '2026-11-10', int $quantity = 3): Sale
{
    $test = test();

    return app(CreateSaleAction::class)->handle($test->admin, [
        'customer_id' => $test->customer->id,
        'date' => '2026-11-01',
        'due_date' => $due,
        'items' => [['variant_id' => $test->variant->id, 'quantity' => $quantity]],
    ]);
}

function screenPayment(string $base, ?User $actor = null): Payment
{
    $test = test();

    return app(CollectPayment::class)->handle($actor ?? $test->admin, [
        'customer_id' => $test->customer->id,
        'payment_method_id' => $test->method->id,
        'base_amount' => $base,
        'date' => '2026-11-16',
    ]);
}

// --- Acceso ---------------------------------------------------------------------------------

it('el administrador y el vendedor ven los cobros y el módulo apagado los oculta', function (string $path) {
    $this->actingAs($this->admin)->get($this->panel.$path)->assertOk();
    $this->actingAs($this->seller)->get($this->panel.$path)->assertOk();

    Modules::disable(Module::CurrentAccount);

    $this->actingAs($this->admin)->get($this->panel.$path)->assertNotFound();
})->with(['/payments', '/payments/create']);

it('el vendedor ve sus cobros y no los de otro vendedor', function () {
    screenAccountSale();
    $mine = screenPayment('500.00', $this->seller);
    $other = screenPayment('300.00', $this->otherSeller);
    $admins = screenPayment('200.00');

    $this->actingAs($this->seller);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other, $admins]);

    $this->actingAs($this->admin);

    Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$mine, $other, $admins]);

    $this->actingAs($this->seller)->get($this->panel.'/payments/'.$other->id)->assertNotFound();
});

it('la lista de cobros muestra solo los cobros a cuenta, no los de las ventas', function () {
    $sale = screenAccountSale();
    $onAccount = screenPayment('500.00');
    $cash = app(CreateSaleAction::class)->handle($this->admin, [
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $this->method->id]],
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$onAccount])
        ->assertCanNotSeeTableRecords($cash->payments)
        ->assertCountTableRecords(1);

    expect($sale->exists)->toBeTrue();
});

// --- Nuevo cobro --------------------------------------------------------------------------------

it('registra un cobro desde el formulario y lo imputa a los vencimientos elegidos', function () {
    $sale = screenAccountSale();
    $installment = $sale->installments()->firstOrFail();
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->method->id,
            'base_amount' => '1200.00',
            'installment_ids' => [$installment->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::query()->firstOrFail();

    expect($payment->base_amount)->toBe('1200.00')
        ->and($payment->sale_id)->toBeNull()
        ->and($installment->refresh()->paid_amount)->toBe('1200.00')
        ->and($installment->status)->toBe(InstallmentStatus::Partial);
});

it('el formulario de cobro arranca con el cliente que viene en el enlace', function () {
    $this->actingAs($this->admin);

    Livewire::withQueryParams(['customer' => $this->customer->id])
        ->test(CreatePayment::class)
        ->assertFormSet(['customer_id' => $this->customer->id]);

    // Sin cliente en el enlace conserva sus valores por defecto (la fecha de hoy).
    Livewire::test(CreatePayment::class)->assertFormSet(['date' => '2026-11-16']);
});

it('muestra el rechazo de un cobro que supera la deuda sin crearlo', function () {
    screenAccountSale();
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'payment_method_id' => $this->method->id,
            'base_amount' => '9999.00',
        ])
        ->call('create')
        ->assertNotified('No se pudo registrar el cobro');

    expect(Payment::count())->toBe(0);
});

it('el formulario exige cliente, medio de pago y un monto', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePayment::class)
        ->fillForm(['customer_id' => null, 'payment_method_id' => null, 'base_amount' => null])
        ->call('create')
        ->assertHasFormErrors(['customer_id' => 'required', 'payment_method_id' => 'required', 'base_amount' => 'required']);
});

// --- Ver y anular un cobro ---------------------------------------------------------------------------

it('la vista del cobro muestra lo imputado y el administrador lo anula con motivo', function () {
    $sale = screenAccountSale();
    $payment = screenPayment('3000.00');
    $this->actingAs($this->admin);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSee($payment->number())
        ->assertSee($sale->number())
        ->assertActionVisible('cancel')
        ->callAction('cancel', ['reason' => 'Cheque rechazado'])
        ->assertNotified('Cobro anulado')
        ->assertActionHidden('cancel');

    expect($payment->refresh()->isCancelled())->toBeTrue()
        ->and($sale->refresh()->status)->toBe(SaleStatus::Active);
});

it('el vendedor ve su cobro pero no puede anularlo', function () {
    screenAccountSale();
    $payment = screenPayment('500.00', $this->seller);
    $this->actingAs($this->seller);

    Livewire::test(ViewPayment::class, ['record' => $payment->getKey()])
        ->assertSee($payment->number())
        ->assertActionHidden('cancel');
});

// --- Ficha del cliente ------------------------------------------------------------------------------

it('la ficha del cliente muestra el saldo y la ven el administrador y el vendedor', function () {
    screenAccountSale();
    screenPayment('1000.00');

    $this->actingAs($this->admin)->get($this->panel.'/customers/'.$this->customer->id)->assertOk()->assertSee('Marta Cliente');
    $this->actingAs($this->seller)->get($this->panel.'/customers/'.$this->customer->id)->assertOk();

    $this->actingAs($this->seller);

    Livewire::test(CustomerAccountStats::class, ['record' => $this->customer])
        ->assertSee('2.000,00')
        // La venta venció el 10/11: todo lo que debe está vencido.
        ->assertSee('Vencido');

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])
        ->assertActionVisible('collect');
});

it('la ficha lista vencimientos, cuenta corriente y productos comprados', function () {
    $sale = screenAccountSale();
    screenPayment('1000.00');
    $installment = $sale->installments()->firstOrFail();
    $this->actingAs($this->admin);

    Livewire::test(InstallmentsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$installment])
        ->assertSee($sale->number());

    Livewire::test(AccountMovementsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertCountTableRecords(2);

    Livewire::test(PurchasedItemsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords($sale->items)
        ->assertSee('Remera');
});

it('la ficha financiera no existe con el módulo apagado', function () {
    Modules::disable(Module::CurrentAccount);

    $this->actingAs($this->admin);

    expect(InstallmentsRelationManager::canViewForRecord($this->customer, ViewCustomer::class))->toBeFalse()
        ->and(AccountMovementsRelationManager::canViewForRecord($this->customer, ViewCustomer::class))->toBeFalse()
        ->and(PurchasedItemsRelationManager::canViewForRecord($this->customer, ViewCustomer::class))->toBeFalse()
        ->and(CustomerAccountStats::canView())->toBeFalse();

    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])->assertActionHidden('collect');
});

// --- Formulario de venta ----------------------------------------------------------------------------

it('crea una venta entera a cuenta desde el formulario', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'date' => '2026-11-16',
            'due_date' => '2026-12-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->status)->toBe(SaleStatus::Active)
        ->and($sale->financed_amount)->toBe('4000.00')
        ->and($sale->first_due_date->toDateString())->toBe('2026-12-16')
        ->and($sale->payments)->toHaveCount(0);
});

it('crea una venta con anticipo desde el formulario', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'date' => '2026-11-16',
            'due_date' => '2026-12-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [['payment_method_id' => $this->method->id, 'base_amount' => '1500.00']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->firstOrFail();

    expect($sale->down_payment)->toBe('1500.00')->and($sale->financed_amount)->toBe('2500.00');
});

it('con saldo a cuenta el formulario exige cliente y vencimiento', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => null,
            'date' => '2026-11-16',
            'due_date' => null,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4]],
            'payments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['customer_id' => 'required', 'due_date' => 'required']);

    expect(Sale::count())->toBe(0);
});

it('sin el módulo el formulario de venta sigue pidiendo al menos un cobro', function () {
    Modules::disable(Module::CurrentAccount);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'date' => '2026-11-16',
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
            'payments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['payments']);

    expect(Sale::count())->toBe(0);
});

it('la vista de la venta muestra su cuenta corriente', function () {
    $sale = screenAccountSale();
    $this->actingAs($this->admin);

    Livewire::test(ViewSale::class, ['record' => $sale->getKey()])
        ->assertSee('Cuenta corriente')
        ->assertSee('Quedó a cuenta')
        ->assertSee('Saldo pendiente');
});

// --- Escritorio ------------------------------------------------------------------------------------

it('el escritorio muestra lo que hay por cobrar y los vencidos con su acceso a cobrar', function () {
    screenAccountSale('2026-11-10');
    screenAccountSale('2026-12-20', 2);
    $this->actingAs($this->admin);

    Livewire::test(AccountOverview::class)
        ->assertSee('Total por cobrar')
        ->assertSee('5.000,00')
        ->assertSee('1 vencimientos atrasados');

    Livewire::test(OverdueInstallments::class)
        ->assertSee('Marta Cliente')
        ->assertSee('6 días')
        ->assertTableActionExists('collect');
});

it('el escritorio de Cuenta corriente es solo del administrador y del módulo encendido', function () {
    $this->actingAs($this->seller);

    expect(AccountOverview::canView())->toBeFalse()->and(OverdueInstallments::canView())->toBeFalse();

    $this->actingAs($this->admin);

    expect(AccountOverview::canView())->toBeTrue()->and(OverdueInstallments::canView())->toBeTrue();

    Modules::disable(Module::CurrentAccount);

    expect(AccountOverview::canView())->toBeFalse()->and(OverdueInstallments::canView())->toBeFalse();
});
