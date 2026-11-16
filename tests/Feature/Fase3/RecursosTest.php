<?php

use App\Actions\Sales\CreateSale;
use App\Enums\ActivityEvent;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->panel = 'http://'.config('domains.panel');
});

function makeSale(User $actor, Product $product, PaymentMethod $method): void
{
    app(CreateSale::class)->handle($actor, [
        'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);
}

// --- Clientes -------------------------------------------------------------

it('el administrador crea, edita y borra clientes, con la actividad antes y después', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Marta Pérez', 'document_number' => '30111222', 'phone' => '1122334455', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('document_number', '30111222')->firstOrFail();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->fillForm(['name' => 'Marta Pérez de Gómez', 'city' => 'Rosario'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = ActivityLog::where('event', ActivityEvent::Updated)->where('subject_id', $customer->id)->firstOrFail();
    expect($log->properties['old']['name'])->toBe('Marta Pérez')
        ->and($log->properties['new']['name'])->toBe('Marta Pérez de Gómez')
        ->and(ActivityLog::where('event', ActivityEvent::Created)->where('subject_id', $customer->id)->exists())->toBeTrue();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$customer])
        ->callTableAction('delete', $customer);

    expect(Customer::find($customer->id))->toBeNull()
        ->and(ActivityLog::where('event', ActivityEvent::Deleted)->where('subject_id', $customer->id)->exists())->toBeTrue();
});

it('el documento del cliente es único', function () {
    $this->actingAs($this->admin);
    Customer::factory()->create(['document_number' => '123']);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['name' => 'Otro', 'document_number' => '123'])
        ->call('create')
        ->assertHasFormErrors(['document_number' => 'unique']);
});

it('el vendedor ve y da de alta clientes pero no los edita ni los borra', function () {
    $this->actingAs($this->seller);
    $customer = Customer::factory()->create();

    $this->get($this->panel.'/customers')->assertOk();
    $this->get($this->panel.'/customers/create')->assertOk();
    $this->get($this->panel.'/customers/'.$customer->id.'/edit')->assertForbidden();

    Livewire::test(ListCustomers::class)
        ->assertTableActionHidden('edit', $customer)
        ->assertTableActionHidden('delete', $customer);
});

it('un cliente con ventas no se borra', function () {
    $this->actingAs($this->admin);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $method = PaymentMethod::factory()->create();

    app(CreateSale::class)->handle($this->admin, [
        'customer_id' => $customer->id,
        'items' => [['variant_id' => $product->implicitVariant->id, 'quantity' => 1]],
        'payments' => [['payment_method_id' => $method->id]],
    ]);

    expect($this->admin->can('delete', $customer))->toBeFalse();

    Livewire::test(ListCustomers::class)->assertTableActionHidden('delete', $customer);
});

// --- Categorías -----------------------------------------------------------

it('el administrador gestiona categorías y el slug sale solo', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Remeras de verano', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::firstOrFail();
    expect($category->slug)->toBe('remeras-de-verano');

    Livewire::test(EditCategory::class, ['record' => $category->id])
        ->fillForm(['name' => 'Remeras', 'slug' => 'remeras'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh()->slug)->toBe('remeras');

    Livewire::test(ListCategories::class)->assertCanSeeTableRecords([$category]);
});

it('el vendedor no accede a las categorías', function () {
    $this->actingAs($this->seller);

    $this->get($this->panel.'/categories')->assertForbidden();
});

// --- Productos ------------------------------------------------------------

it('crea un producto desde el panel con SKU automático y su variante implícita', function () {
    $this->actingAs($this->admin);
    $category = Category::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Remera lisa', 'category_id' => $category->id, 'price' => '1500.00', 'cost' => '900.00', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    expect($product->sku)->toBe('PROD-00001')
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->name)->toBe('')
        ->and($product->category_id)->toBe($category->id);
});

it('edita un producto y conserva el SKU si se deja vacío', function () {
    $this->actingAs($this->admin);
    $product = Product::factory()->create(['sku' => 'ABC-1']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['name' => 'Nuevo nombre', 'sku' => '', 'price' => '2000.00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->sku)->toBe('ABC-1')
        ->and($product->fresh()->name)->toBe('Nuevo nombre');
});

it('el SKU del producto es único', function () {
    $this->actingAs($this->admin);
    Product::factory()->create(['sku' => 'DUP-1']);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Otro', 'sku' => 'DUP-1', 'price' => '1.00', 'cost' => '0.00'])
        ->call('create')
        ->assertHasFormErrors(['sku' => 'unique']);
});

it('el vendedor ve los productos pero no su costo ni puede gestionarlos', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin);
    Livewire::test(ListProducts::class)->assertTableColumnVisible('cost');

    $this->actingAs($this->seller);
    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$product])
        ->assertTableColumnHidden('cost')
        ->assertTableActionHidden('edit', $product)
        ->assertTableActionHidden('delete', $product);

    $this->get($this->panel.'/products/create')->assertForbidden();
    $this->get($this->panel.'/products/'.$product->id.'/edit')->assertForbidden();
});

it('un producto vendido no se borra', function () {
    $this->actingAs($this->admin);
    $product = Product::factory()->create();
    makeSale($this->admin, $product, PaymentMethod::factory()->create());

    expect($this->admin->can('delete', $product))->toBeFalse();
    Livewire::test(ListProducts::class)->assertTableActionHidden('delete', $product);

    $free = Product::factory()->create();
    expect($this->admin->can('delete', $free))->toBeTrue();
});

// --- Medios de pago -------------------------------------------------------

it('el administrador gestiona los medios de pago', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePaymentMethod::class)
        ->fillForm(['name' => 'Tarjeta 12 cuotas', 'installments' => 12, 'adjustment_percent' => '45.5', 'active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $method = PaymentMethod::where('name', 'Tarjeta 12 cuotas')->firstOrFail();
    expect($method->installments)->toBe(12)
        ->and($method->adjustment_percent)->toBe('45.50');

    Livewire::test(EditPaymentMethod::class, ['record' => $method->id])
        ->fillForm(['adjustment_percent' => '-5'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($method->fresh()->adjustment_percent)->toBe('-5.00');

    Livewire::test(ListPaymentMethods::class)->assertCanSeeTableRecords([$method]);
});

it('las cuotas del medio de pago son al menos 1', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreatePaymentMethod::class)
        ->fillForm(['name' => 'Raro', 'installments' => 0, 'adjustment_percent' => '0'])
        ->call('create')
        ->assertHasFormErrors(['installments']);
});

it('el vendedor no accede a los medios de pago', function () {
    $this->actingAs($this->seller);

    $this->get($this->panel.'/payment-methods')->assertForbidden();
});

it('un medio de pago usado en cobros no se borra', function () {
    $this->actingAs($this->admin);
    $product = Product::factory()->create();
    $used = PaymentMethod::factory()->create();
    makeSale($this->admin, $product, $used);

    expect($this->admin->can('delete', $used))->toBeFalse();
    expect($this->admin->can('delete', PaymentMethod::factory()->create()))->toBeTrue();
});

it('el etiquetado del medio de pago muestra su recargo o descuento', function () {
    expect(PaymentMethod::factory()->make(['name' => 'Efectivo', 'adjustment_percent' => '-10.00'])->label())->toBe('Efectivo (−10 %)')
        ->and(PaymentMethod::factory()->make(['name' => 'Tarjeta 3 cuotas', 'adjustment_percent' => '15.00'])->label())->toBe('Tarjeta 3 cuotas (+15 %)')
        ->and(PaymentMethod::factory()->make(['name' => 'Tarjeta', 'adjustment_percent' => '12.50'])->label())->toBe('Tarjeta (+12,5 %)')
        ->and(PaymentMethod::factory()->make(['name' => 'Transferencia', 'adjustment_percent' => '0.00'])->label())->toBe('Transferencia');
});
