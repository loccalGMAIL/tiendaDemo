<?php

use App\Actions\Sales\FindVariantByCode;
use App\Actions\Variants\AssignMissingVariantSkus;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\BarcodeLabels;
use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\PrintableSku;
use App\Services\Barcodes\LabelLayout;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();
    Modules::enable(Module::Variants);
    Modules::enable(Module::Barcodes);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->panel = 'http://'.config('domains.panel');

    $this->product = Product::factory()->priced('1500.00', '800.00')->create(['name' => 'Remera lisa', 'sku' => 'REM-1']);
    $this->variantM = ProductVariant::factory()->create(['product_id' => $this->product->id, 'name' => 'M / Negro', 'sku' => 'REM-1-M']);
    $this->variantL = ProductVariant::factory()->create(['product_id' => $this->product->id, 'name' => 'L / Negro', 'sku' => 'REM-1-L']);

    $this->simple = Product::factory()->priced('900.00', '500.00')->create(['name' => 'Cinturón', 'sku' => 'CINT-1']);
    $this->simpleVariant = $this->simple->implicitVariant;

    PaymentMethod::factory()->create();
});

// --- Módulo apagado y permisos ---------------------------------------------

it('con el módulo apagado la impresión, la página y el campo de escaneo no existen', function () {
    Modules::disable(Module::Barcodes);

    $this->actingAs($this->admin)->get($this->panel.'/etiquetas/imprimir?v='.$this->variantM->id.':1')->assertNotFound();
    $this->actingAs($this->admin)->get($this->panel.'/barcode-labels')->assertNotFound();

    Livewire::actingAs($this->admin)->test(CreateSale::class)->assertFormFieldIsHidden('scan');
});

it('el vendedor no puede imprimir etiquetas ni ver la página', function () {
    $this->actingAs($this->seller)->get($this->panel.'/etiquetas/imprimir?v='.$this->variantM->id.':1')->assertForbidden();
    $this->actingAs($this->seller)->get($this->panel.'/barcode-labels')->assertForbidden();
});

it('sin sesión la impresión manda al login', function () {
    $this->get($this->panel.'/etiquetas/imprimir?v=1:1')->assertRedirect();
});

// --- Impresión -------------------------------------------------------------

it('imprime una etiqueta por cantidad pedida, con su código de barras y el formato de rollo', function () {
    $response = $this->actingAs($this->admin)
        ->get($this->panel.'/etiquetas/imprimir?v='.$this->variantM->id.':3,'.$this->simpleVariant->id.':1')
        ->assertOk()
        ->assertSee('size: 50mm 25mm', false)
        ->assertSee('Remera lisa')
        ->assertSee('M / Negro')
        ->assertSee('REM-1-M')
        ->assertSee('CINT-1')
        ->assertSee('Imprimir 4 etiquetas');

    expect(substr_count($response->getContent(), '<svg'))->toBe(4)
        ->and(substr_count($response->getContent(), 'class="page"'))->toBe(4);
});

it('en hojas arma la grilla y respeta la posición de la primera etiqueta', function () {
    Modules::updateSettings(Module::Barcodes, [
        ...LabelLayout::presets()['a4_3x8']['values'],
        'preset' => 'a4_3x8',
    ]);

    $response = $this->actingAs($this->admin)
        ->get($this->panel.'/etiquetas/imprimir?start=3&v='.$this->variantM->id.':2')
        ->assertOk()
        ->assertSee('size: A4', false)
        ->assertSee('repeat(3, 70mm)', false);

    // Una sola hoja: 2 lugares vacíos al principio y 2 etiquetas.
    expect(substr_count($response->getContent(), 'class="page"'))->toBe(1)
        ->and(substr_count($response->getContent(), '<svg'))->toBe(2)
        ->and(substr_count($response->getContent(), '<div class="label"></div>'))->toBe(2);
});

it('salta las variantes sin código y lo avisa', function () {
    $withoutSku = ProductVariant::factory()->create(['product_id' => $this->product->id, 'name' => 'XL / Negro']);
    ProductVariant::query()->whereKey($withoutSku->id)->update(['sku' => null]);

    $response = $this->actingAs($this->admin)
        ->get($this->panel.'/etiquetas/imprimir?v='.$withoutSku->id.':2,'.$this->variantM->id.':1')
        ->assertOk()
        ->assertSee('Sin código (no se imprimen)')
        ->assertSee('XL / Negro');

    expect(substr_count($response->getContent(), '<svg'))->toBe(1);
});

it('ignora pedidos mal formados y no pasa del máximo por variante', function () {
    $response = $this->actingAs($this->admin)
        ->get($this->panel.'/etiquetas/imprimir?v=abc,0:3,'.$this->variantM->id.':99999')
        ->assertOk();

    expect(substr_count($response->getContent(), '<svg'))->toBe(500);
});

// --- SKU de las variantes --------------------------------------------------

it('una variante con nombre sin SKU toma el del producto con sufijo', function () {
    $product = Product::factory()->create(['sku' => 'PROD-00077']);

    $first = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'S']);
    $second = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'M']);

    expect($first->sku)->toBe('PROD-00077-01')
        ->and($second->sku)->toBe('PROD-00077-02');
});

it('las variantes generadas nacen con SKU y la implícita usa el del producto', function () {
    Modules::updateSettings(Module::Variants, ['attributes' => [['name' => 'Talle', 'values' => ['S', 'M']]]]);
    $product = Product::factory()->create(['sku' => 'GEN-1']);

    app(GenerateVariants::class)->handle($product, ['Talle' => ['S', 'M']], $this->admin);

    expect($product->variants()->where('name', '!=', '')->orderBy('position')->pluck('sku')->all())->toBe(['GEN-1-01', 'GEN-1-02'])
        ->and($product->implicitVariant->barcode())->toBe('GEN-1');
});

it('completa los SKU que faltan sin tocar los que ya existen', function () {
    $product = Product::factory()->create(['sku' => 'OLD-1']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Única']);
    ProductVariant::query()->whereKey($variant->id)->update(['sku' => null]);

    expect(app(AssignMissingVariantSkus::class)->handle())->toBe(1)
        ->and($variant->fresh()->sku)->toBe('OLD-1-01')
        ->and($this->variantM->fresh()->sku)->toBe('REM-1-M')
        ->and(app(AssignMissingVariantSkus::class)->handle())->toBe(0);

    $this->artisan('barcodes:fill-skus')->assertSuccessful();
});

it('el SKU tiene que poder imprimirse y no repetirse entre productos y variantes', function () {
    $rule = fn (PrintableSku $rule, string $sku): bool => Validator::make(['sku' => $sku], ['sku' => [$rule]])->passes();

    expect($rule(PrintableSku::forVariant(), 'OK-123'))->toBeTrue()
        ->and($rule(PrintableSku::forVariant(), 'Camión'))->toBeFalse()
        ->and($rule(PrintableSku::forVariant(), 'REM-1'))->toBeFalse()
        ->and($rule(PrintableSku::forProduct(), 'REM-1-M'))->toBeFalse()
        ->and($rule(PrintableSku::forProduct(), 'NUEVO-1'))->toBeTrue();
});

// --- Búsqueda por código ---------------------------------------------------

it('encuentra la variante por su SKU o el producto simple por el suyo', function () {
    $find = app(FindVariantByCode::class);

    expect($find->handle(' REM-1-L ')->id)->toBe($this->variantL->id)
        ->and($find->handle('CINT-1')->id)->toBe($this->simpleVariant->id);
});

it('rechaza un código desconocido, el de un producto con variantes y el de un producto inactivo', function () {
    $find = app(FindVariantByCode::class);

    expect(fn () => $find->handle('NO-EXISTE'))->toThrow(ValidationException::class, 'No hay ningún producto')
        ->and(fn () => $find->handle('REM-1'))->toThrow(ValidationException::class, 'escaneá la etiqueta de la variante');

    $this->simple->update(['active' => false]);

    expect(fn () => $find->handle('CINT-1'))->toThrow(ValidationException::class, 'no está disponible');
});

// --- Venta con lector ------------------------------------------------------

it('al escanear agrega la variante con su precio y suma una unidad si se repite', function () {
    $component = Livewire::actingAs($this->admin)->test(CreateSale::class)->assertFormFieldIsVisible('scan');

    $component->set('data.scan', 'REM-1-M');

    $items = array_values($component->get('data.items'));

    expect($items)->toHaveCount(1)
        ->and($items[0]['variant_id'])->toBe($this->variantM->id)
        ->and((int) $items[0]['quantity'])->toBe(1)
        ->and((float) $items[0]['unit_price'])->toBe(1500.0)
        ->and($component->get('data.scan'))->toBeNull();

    $component->set('data.scan', 'REM-1-M')->set('data.scan', 'CINT-1');

    $items = collect($component->get('data.items'))->keyBy('variant_id');

    expect($items)->toHaveCount(2)
        ->and((int) $items[$this->variantM->id]['quantity'])->toBe(2)
        ->and((int) $items[$this->simpleVariant->id]['quantity'])->toBe(1);
});

it('un código desconocido avisa y no cambia la venta', function () {
    $component = Livewire::actingAs($this->admin)->test(CreateSale::class);
    $before = $component->get('data.items');

    $component->set('data.scan', 'NO-EXISTE')->assertNotified();

    expect($component->get('data.items'))->toEqual($before);
});

// --- Página de etiquetas ---------------------------------------------------

it('la página llega con la selección cargada y arma el enlace de impresión', function () {
    $component = Livewire::actingAs($this->admin)
        ->withQueryParams(['v' => $this->variantM->id.':2,'.$this->simpleVariant->id.':1'])
        ->test(BarcodeLabels::class);

    expect($component->instance()->getTotalLabels())->toBe(3)
        ->and($component->instance()->getPrintUrl())->toContain('/etiquetas/imprimir')
        ->and($component->instance()->getPrintUrl())->toContain(rawurlencode($this->variantM->id.':2'));
});

it('«1 de cada una» y «Limpiar» cargan y vacían las cantidades', function () {
    $component = Livewire::actingAs($this->admin)->test(BarcodeLabels::class)->set('search', 'Remera')->call('oneOfEach');

    expect($component->instance()->getRequested())->toBe([$this->variantM->id => 1, $this->variantL->id => 1]);

    $component->call('clearQuantities');

    expect($component->instance()->getRequested())->toBe([]);
});

it('los accesos desde producto y compra arman la selección de variantes vendibles', function () {
    expect(BarcodeLabels::selectionForProducts([$this->product, $this->simple]))->toBe([
        $this->variantM->id => 1,
        $this->variantL->id => 1,
        $this->simpleVariant->id => 1,
    ]);
});

// --- Formato ---------------------------------------------------------------

it('el formato se arma desde los ajustes y se corrige lo fuera de rango', function () {
    $layout = LabelLayout::fromSettings(['format' => 'sheet', 'paper' => 'letter', 'label_width' => 5, 'label_height' => 40, 'columns' => 3, 'rows' => 8]);

    expect($layout->isSheet())->toBeTrue()
        ->and($layout->width)->toBe(10.0)
        ->and($layout->perPage())->toBe(24)
        ->and($layout->pageSize())->toBe('letter');

    $roll = LabelLayout::fromSettings(['format' => 'roll', 'label_width' => 50, 'label_height' => 25, 'columns' => 4]);

    expect($roll->perPage())->toBe(1)->and($roll->pageSize())->toBe('50mm 25mm');
});
