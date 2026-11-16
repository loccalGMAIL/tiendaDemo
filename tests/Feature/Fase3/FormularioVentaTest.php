<?php

use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Facades\Modules;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Schemas\SaleForm;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();

    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa', 'sku' => 'REM-1']);
    $this->variant = $this->product->implicitVariant;

    $this->cash = PaymentMethod::factory()->create(['name' => 'Efectivo', 'adjustment_percent' => '-10.00']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00']);
});

it('muestra el formulario de nueva venta a administradores y vendedores', function () {
    foreach ([$this->admin, $this->seller] as $user) {
        $this->actingAs($user)
            ->get('http://'.config('domains.panel').'/sales/create')
            ->assertOk()
            ->assertSee('Nueva venta');
    }
});

it('confirma una venta de contado desde el formulario y lleva a su vista', function () {
    $this->actingAs($this->admin);
    $customer = Customer::factory()->create();

    $component = Livewire::test(CreateSale::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'seller_id' => $this->seller->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::firstOrFail();

    // 2 × 1000 = 2000; efectivo −10 % → 1800
    expect($sale->customer_id)->toBe($customer->id)
        ->and($sale->seller_id)->toBe($this->seller->id)
        ->and($sale->total)->toBe('1800.00')
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and($sale->items)->toHaveCount(1);

    $component->assertRedirect(SaleResource::getUrl('view', ['record' => $sale]));
});

it('confirma una venta con pago combinado', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'seller_id' => $this->admin->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'base_amount' => '1000.00'],
                ['payment_method_id' => $this->card->id, 'base_amount' => '2000.00'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::firstOrFail();

    expect($sale->payments)->toHaveCount(2)
        ->and($sale->total)->toBe('3200.00');
});

it('un vendedor confirma la venta a su nombre', function () {
    $this->actingAs($this->seller);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->card->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::firstOrFail();

    expect($sale->seller_id)->toBe($this->seller->id)
        ->and($sale->total)->toBe('1150.00')
        ->and($sale->payments->first()->collected_by)->toBe($this->seller->id);
});

it('un vendedor que manda otro precio no logra cambiarlo', function () {
    $this->actingAs($this->seller);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'unit_price' => '1.00']],
            'payments' => [['payment_method_id' => $this->cash->id]],
        ])
        ->call('create')
        ->assertNotified('No se pudo confirmar la venta');

    expect(Sale::count())->toBe(0);
});

it('muestra el aviso y no crea la venta si los cobros no suman el subtotal', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'seller_id' => $this->admin->id,
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'base_amount' => '1000.00'],
                ['payment_method_id' => $this->card->id, 'base_amount' => '1000.00'],
            ],
        ])
        ->call('create')
        ->assertNotified('No se pudo confirmar la venta');

    expect(Sale::count())->toBe(0);
});

it('exige al menos un producto y un medio de pago', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm(['seller_id' => $this->admin->id, 'items' => [], 'payments' => []])
        ->call('create')
        ->assertHasFormErrors(['items', 'payments']);

    expect(Sale::count())->toBe(0);
});

it('propone el vendedor por defecto al administrador', function () {
    $default = User::factory()->seller()->create(['is_default_seller' => true]);

    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)->assertFormSet(['seller_id' => $default->id]);
});

it('sin vendedor por defecto, el administrador arranca con él mismo; el vendedor siempre con el suyo', function () {
    $this->actingAs($this->admin);
    Livewire::test(CreateSale::class)->assertFormSet(['seller_id' => $this->admin->id]);

    $default = User::factory()->seller()->create(['is_default_seller' => true]);
    $this->actingAs($this->seller);
    Livewire::test(CreateSale::class)->assertFormSet(['seller_id' => $this->seller->id]);
});

it('el vendedor por defecto inactivo no se propone', function () {
    User::factory()->seller()->inactive()->create(['is_default_seller' => true]);

    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)->assertFormSet(['seller_id' => $this->admin->id]);
});

it('el resumen en vivo muestra subtotal, recargo y total con el mismo cálculo de la venta', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $this->card->id]],
        ])
        ->assertSee('Subtotal: $ 2.000,00')
        ->assertSee('Recargos y descuentos: $ 300,00')
        ->assertSee('Total a cobrar: $ 2.300,00');
});

it('el resumen avisa cuando la suma de los montos no coincide con el subtotal', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']],
            'payments' => [
                ['payment_method_id' => $this->cash->id, 'base_amount' => '1000.00'],
                ['payment_method_id' => $this->card->id, 'base_amount' => '500.00'],
            ],
        ])
        ->assertSee('no coincide con el subtotal');
});

it('el producto se busca por nombre o SKU y solo aparecen los activos', function () {
    Product::factory()->inactive()->create(['name' => 'Remera vieja', 'sku' => 'REM-OLD']);

    $byName = SaleForm::searchVariants('Remera');
    $bySku = SaleForm::searchVariants('REM-1');

    expect($byName)->toHaveCount(1)
        ->and(array_values($byName)[0])->toStartWith('Remera lisa · REM-1')
        ->and($bySku)->toHaveKey($this->variant->id)
        ->and(SaleForm::searchVariants('no-existe'))->toBe([]);
});

it('con Variantes apagado solo se ofrecen las implícitas y con Variantes encendido las que tienen nombre', function () {
    resetModules();
    $named = $this->product->variants()->create(['name' => 'M / Negro', 'position' => 1]);

    expect(SaleForm::searchVariants(''))->toHaveKey($this->variant->id)->not->toHaveKey($named->id);

    Modules::enable(Module::Variants);

    // Con variantes cargadas, la implícita deja de ofrecerse.
    expect(SaleForm::searchVariants(''))->toHaveKey($named->id)->not->toHaveKey($this->variant->id);
});
