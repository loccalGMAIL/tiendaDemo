<?php

use App\Actions\Stock\AdjustStock;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Filament\Pages\StockCount;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Schemas\SaleForm;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Superadmin\Pages\ManageModules;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\StockOverview;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Stock);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->panel = 'http://'.config('domains.panel');

    $this->category = Category::factory()->create(['name' => 'Remeras']);
    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa', 'sku' => 'REM-1', 'category_id' => $this->category->id]);
    $this->variant = $this->product->implicitVariant;

    PaymentMethod::factory()->create();
});

// --- Carga de stock -------------------------------------------------------

it('el administrador ve la carga de stock; el vendedor no', function () {
    $this->actingAs($this->admin)->get($this->panel.'/stock-count')->assertOk()->assertSee('Carga de stock');
    $this->actingAs($this->seller)->get($this->panel.'/stock-count')->assertForbidden();
});

it('con Stock apagado la carga de stock no existe', function () {
    Modules::disable(Module::Stock);

    $this->actingAs($this->admin)->get($this->panel.'/stock-count')->assertNotFound();
});

it('guarda el recuento de la grilla con su motivo', function () {
    $other = Product::factory()->create(['name' => 'Pantalón']);
    $this->actingAs($this->admin);

    Livewire::test(StockCount::class)
        ->set('reason', 'Stock inicial')
        ->set('counts.'.$this->variant->id, '12')
        ->set('counts.'.$other->implicitVariant->id, '')
        ->set('minimums.'.$this->variant->id, '4')
        ->call('save')
        ->assertNotified()
        ->assertSet('counts', []);

    $movement = StockMovement::firstOrFail();

    expect($this->variant->fresh()->stock)->toBe(12)
        ->and($this->variant->fresh()->min_stock)->toBe(4)
        ->and($other->implicitVariant->fresh()->stock)->toBe(0)
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->reason)->toBe('Stock inicial')
        ->and($movement->user_id)->toBe($this->admin->id)
        ->and(StockMovement::count())->toBe(1);
});

it('el recuento sin diferencias no registra nada', function () {
    app(AdjustStock::class)->handle($this->variant, 5, 'Stock inicial', $this->admin);
    $this->actingAs($this->admin);

    Livewire::test(StockCount::class)
        ->set('counts.'.$this->variant->id, '5')
        ->call('save')
        ->assertNotified('No había diferencias para registrar');

    expect(StockMovement::count())->toBe(1);
});

it('avisa y no guarda si falta el motivo o hay un valor inválido', function () {
    $this->actingAs($this->admin);

    Livewire::test(StockCount::class)
        ->set('reason', '')
        ->set('counts.'.$this->variant->id, '3')
        ->call('save')
        ->assertNotified();

    Livewire::test(StockCount::class)
        ->set('counts.'.$this->variant->id, '-2')
        ->call('save')
        ->assertNotified();

    expect(StockMovement::count())->toBe(0)->and($this->variant->fresh()->stock)->toBe(0);
});

it('la grilla muestra solo variantes vendibles y se filtra por categoría y búsqueda', function () {
    $other = Product::factory()->create(['name' => 'Campera', 'category_id' => Category::factory()->create(['name' => 'Abrigos'])->id]);
    $inactive = Product::factory()->inactive()->create(['name' => 'Producto viejo']);
    $this->actingAs($this->admin);

    $all = Livewire::test(StockCount::class)->instance()->getVariants()->pluck('id')->all();

    expect($all)->toContain($this->variant->id, $other->implicitVariant->id)
        ->not->toContain($inactive->implicitVariant->id);

    $byCategory = Livewire::test(StockCount::class)->set('categoryId', $this->category->id)->instance()->getVariants()->pluck('id')->all();
    expect($byCategory)->toBe([$this->variant->id]);

    $bySearch = Livewire::test(StockCount::class)->set('search', 'Campera')->instance()->getVariants()->pluck('id')->all();
    expect($bySearch)->toBe([$other->implicitVariant->id]);

    $bySku = Livewire::test(StockCount::class)->set('search', 'REM-1')->instance()->getVariants()->pluck('id')->all();
    expect($bySku)->toBe([$this->variant->id]);
});

it('con Variantes encendido la grilla lista las variantes y no la implícita', function () {
    Modules::enable(Module::Variants);
    $named = $this->product->variants()->create(['name' => 'M', 'position' => 1]);
    $this->actingAs($this->admin);

    $ids = Livewire::test(StockCount::class)->instance()->getVariants()->pluck('id')->all();

    expect($ids)->toContain($named->id)->not->toContain($this->variant->id);
});

it('la pantalla de carga muestra el stock actual de cada variante', function () {
    app(AdjustStock::class)->handle($this->variant, 7, 'Stock inicial', $this->admin);

    $this->actingAs($this->admin)
        ->get($this->panel.'/stock-count')
        ->assertOk()
        ->assertSee('Remera lisa')
        ->assertSee('REM-1');
});

// --- Historial ------------------------------------------------------------

it('el historial lista los movimientos con filtros por tipo y producto', function () {
    $other = Product::factory()->create();
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    app(AdjustStock::class)->handle($other->implicitVariant, 4, 'Stock inicial', $this->admin);
    sellVariant($this->seller, $this->variant, 2);

    $this->actingAs($this->admin);

    $adjustment = StockMovement::where('type', StockMovementType::Adjustment->value)->first();
    $sale = StockMovement::where('type', StockMovementType::Sale->value)->firstOrFail();

    Livewire::test(ListStockMovements::class)
        ->assertCanSeeTableRecords(StockMovement::all())
        ->filterTable('type', StockMovementType::Sale->value)
        ->assertCanSeeTableRecords([$sale])
        ->assertCanNotSeeTableRecords([$adjustment]);

    Livewire::test(ListStockMovements::class)
        ->filterTable('product', $other->id)
        ->assertCanSeeTableRecords(StockMovement::where('product_variant_id', $other->implicitVariant->id)->get())
        ->assertCanNotSeeTableRecords([$sale]);
});

it('el historial solo lo ve el administrador y no existe con Stock apagado', function () {
    $this->actingAs($this->seller)->get($this->panel.'/stock-movements')->assertForbidden();
    $this->actingAs($this->admin)->get($this->panel.'/stock-movements')->assertOk();

    Modules::disable(Module::Stock);

    $this->actingAs($this->admin)->get($this->panel.'/stock-movements')->assertNotFound();
});

// --- Escritorio -----------------------------------------------------------

it('el escritorio muestra el valor del stock, el stock bajo y los negativos', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $this->variant->update(['min_stock' => 12]);

    $cheap = Product::factory()->priced('50.00', '100.00')->create(['name' => 'Medias']);
    sellVariant($this->admin, $cheap->implicitVariant, 2);

    $this->actingAs($this->admin);

    // 10 × 600 = 6.000 (el stock negativo no suma)
    Livewire::test(StockOverview::class)
        ->assertSee('Valor del stock')
        ->assertSee('$ 6.000,00')
        ->assertSee('Productos con stock bajo')
        ->assertSee('Variantes con stock negativo');

    Livewire::test(LowStockTable::class)
        ->assertSee('Remera lisa')
        ->assertDontSee('Medias');
});

it('los widgets de stock solo los ve el administrador y con el módulo encendido', function () {
    $this->actingAs($this->admin);
    expect(StockOverview::canView())->toBeTrue()->and(LowStockTable::canView())->toBeTrue();

    $this->actingAs($this->seller);
    expect(StockOverview::canView())->toBeFalse()->and(LowStockTable::canView())->toBeFalse();

    $this->actingAs($this->admin);
    Modules::disable(Module::Stock);
    expect(StockOverview::canView())->toBeFalse()->and(LowStockTable::canView())->toBeFalse();
});

it('el stock bajo no cuenta las variantes sin mínimo definido', function () {
    $this->variant->update(['min_stock' => 0]);

    $this->actingAs($this->admin);

    Livewire::test(StockOverview::class)->assertSee('Ninguno llegó a su mínimo');
});

// --- Productos y venta ----------------------------------------------------

it('la lista de productos muestra la existencia total solo con Stock encendido', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListProducts::class)->assertTableColumnVisible('variants_sum_stock');

    Modules::disable(Module::Stock);

    Livewire::test(ListProducts::class)->assertTableColumnHidden('variants_sum_stock');
});

it('al elegir producto en la venta se ve la existencia de cada variante', function () {
    app(AdjustStock::class)->handle($this->variant, 7, 'Stock inicial', $this->admin);

    expect(array_values(SaleForm::searchVariants('Remera'))[0])->toBe('Remera lisa · REM-1 · stock 7');

    Modules::disable(Module::Stock);

    expect(array_values(SaleForm::searchVariants('Remera'))[0])->toBe('Remera lisa · REM-1');
});

it('el formulario de venta avisa cuando se pide más de lo que hay', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']]])
        ->assertSee('Stock disponible: 10');

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['variant_id' => $this->variant->id, 'quantity' => 20, 'unit_price' => '1000.00']]])
        ->assertSee('Pediste 20 y hay 10')
        ->assertSee('el stock queda en negativo');

    Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block']);

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['variant_id' => $this->variant->id, 'quantity' => 20, 'unit_price' => '1000.00']]])
        ->assertSee('La venta se rechazará si no alcanza');
});

it('con Stock apagado el formulario de venta no muestra existencias', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    Modules::disable(Module::Stock);
    $this->actingAs($this->admin);

    Livewire::test(CreateSale::class)
        ->fillForm(['items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']]])
        ->assertDontSee('Stock disponible');
});

it('desde el formulario, en modo bloquear la venta sin stock se rechaza con aviso', function () {
    Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block']);
    app(AdjustStock::class)->handle($this->variant, 1, 'Stock inicial', $this->admin);
    $method = PaymentMethod::first();
    $this->actingAs($this->seller);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 3, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $method->id]],
        ])
        ->call('create')
        ->assertNotified('No se pudo confirmar la venta');

    expect(Sale::count())->toBe(0)->and($this->variant->fresh()->stock)->toBe(1);
});

it('desde el formulario la venta descuenta el stock', function () {
    app(AdjustStock::class)->handle($this->variant, 10, 'Stock inicial', $this->admin);
    $method = PaymentMethod::first();
    $this->actingAs($this->seller);

    Livewire::test(CreateSale::class)
        ->fillForm([
            'items' => [['variant_id' => $this->variant->id, 'quantity' => 4, 'unit_price' => '1000.00']],
            'payments' => [['payment_method_id' => $method->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($this->variant->fresh()->stock)->toBe(6);
});

// --- Panel superadmin -----------------------------------------------------

it('el panel superadmin advierte de las variantes en negativo al elegir bloquear', function () {
    sellVariant($this->admin, $this->variant, 2);

    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(ManageModules::class)
        ->assertSee('Conviene dejarlo en «Avisar»')
        ->set('data.modules.Stock.settings.on_no_stock', 'block')
        ->assertSee('hay 1 variantes con stock negativo');
});
