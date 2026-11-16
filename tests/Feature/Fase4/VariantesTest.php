<?php

use App\Actions\Stock\AdjustStock;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();

    Modules::enable(Module::Variants);
    Modules::updateSettings(Module::Variants, ['attributes' => [
        ['name' => 'Talle', 'values' => ['S', 'M', 'L', 'XL']],
        ['name' => 'Color', 'values' => ['Negro', 'Blanco']],
    ]]);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->product = Product::factory()->priced('1000.00', '600.00')->create(['name' => 'Remera lisa']);
});

function generate(Product $product, array $selection, ?User $actor = null): int
{
    return app(GenerateVariants::class)->handle($product, $selection, $actor ?? User::factory()->admin()->create());
}

// --- Generación -----------------------------------------------------------

it('genera todas las combinaciones de los valores elegidos', function () {
    $created = generate($this->product, ['Talle' => ['S', 'M', 'L'], 'Color' => ['Negro', 'Blanco']]);

    $named = $this->product->variants()->where('name', '!=', '')->get();

    expect($created)->toBe(6)
        ->and($named->pluck('name')->all())->toBe([
            'S / Negro', 'S / Blanco', 'M / Negro', 'M / Blanco', 'L / Negro', 'L / Blanco',
        ])
        ->and($named->first()->getAttribute('attributes'))->toBe(['Talle' => 'S', 'Color' => 'Negro'])
        ->and($named->every(fn (ProductVariant $v) => $v->active && $v->stock === 0 && $v->price === null))->toBeTrue();
});

it('con un solo atributo genera una variante por valor', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);

    expect($this->product->variants()->where('name', '!=', '')->pluck('name')->all())->toBe(['S', 'M']);
});

it('no duplica las variantes que ya existen', function () {
    generate($this->product, ['Talle' => ['S', 'M'], 'Color' => ['Negro']]);

    expect(generate($this->product, ['Talle' => ['S', 'M'], 'Color' => ['Negro']]))->toBe(0)
        ->and($this->product->variants()->where('name', '!=', '')->count())->toBe(2);
});

it('al sumar un valor crea solo las combinaciones nuevas', function () {
    generate($this->product, ['Talle' => ['S', 'M'], 'Color' => ['Negro', 'Blanco']]);

    $created = generate($this->product, ['Talle' => ['S', 'M', 'XL'], 'Color' => ['Negro', 'Blanco']]);

    expect($created)->toBe(2)
        ->and($this->product->variants()->where('name', '!=', '')->count())->toBe(6)
        ->and($this->product->variants()->where('name', 'XL / Negro')->exists())->toBeTrue();
});

it('conserva la variante implícita al generar', function () {
    generate($this->product, ['Talle' => ['S']]);

    $implicit = $this->product->variants()->where('name', '')->first();

    expect($implicit)->not->toBeNull()->and($implicit->active)->toBeTrue();
});

it('rechaza una selección vacía, un atributo o un valor que no están configurados', function (array $selection, string $message) {
    expect(fn () => generate($this->product, $selection))->toThrow(ValidationException::class, $message);

    expect($this->product->variants()->where('name', '!=', '')->count())->toBe(0);
})->with([
    'vacía' => [[], 'al menos un valor'],
    'sin valores' => [['Talle' => []], 'al menos un valor'],
    'atributo desconocido' => [['Material' => ['Algodón']], 'no está configurado'],
    'valor desconocido' => [['Talle' => ['XXXL']], 'no es un valor de Talle'],
]);

it('solo un administrador genera variantes y solo con el módulo encendido', function () {
    expect(fn () => generate($this->product, ['Talle' => ['S']], $this->seller))->toThrow(AuthorizationException::class);

    Modules::disable(Module::Variants);

    expect(fn () => generate($this->product, ['Talle' => ['S']]))->toThrow(ValidationException::class, 'apagado');
});

// --- Vendibles ------------------------------------------------------------

it('con el módulo encendido, la implícita solo se ofrece si el producto no tiene variantes con nombre', function () {
    $plain = Product::factory()->create();

    generate($this->product, ['Talle' => ['S', 'M']]);

    $sellable = ProductVariant::query()->sellable()->pluck('name', 'id');

    expect($sellable)->toHaveCount(3)
        ->and($sellable->values()->all())->toContain('S', 'M', '')
        ->and(ProductVariant::query()->sellable()->where('product_id', $this->product->id)->pluck('name')->all())->toBe(['S', 'M'])
        ->and(ProductVariant::query()->sellable()->where('product_id', $plain->id)->count())->toBe(1);
});

it('con el módulo apagado solo se ofrece la implícita', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);

    Modules::disable(Module::Variants);

    $sellable = ProductVariant::query()->sellable()->where('product_id', $this->product->id)->get();

    expect($sellable)->toHaveCount(1)->and($sellable->first()->isImplicit())->toBeTrue();
});

it('no se ofrecen variantes inactivas ni de productos inactivos', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);
    $this->product->variants()->where('name', 'S')->update(['active' => false]);

    expect(ProductVariant::query()->sellable()->where('product_id', $this->product->id)->pluck('name')->all())->toBe(['M']);

    $this->product->update(['active' => false]);

    expect(ProductVariant::query()->sellable()->where('product_id', $this->product->id)->count())->toBe(0);
});

// --- Borrado --------------------------------------------------------------

it('una variante con ventas o movimientos no se borra, sin historia sí', function () {
    generate($this->product, ['Talle' => ['S', 'M', 'L']]);
    [$free, $sold, $moved] = $this->product->variants()->where('name', '!=', '')->get()->all();

    sellVariant($this->admin, $sold);
    Modules::enable(Module::Stock);
    app(AdjustStock::class)->handle($moved, 5, 'Stock inicial', $this->admin);

    expect($this->admin->can('delete', $free))->toBeTrue()
        ->and($this->admin->can('delete', $sold))->toBeFalse()
        ->and($this->admin->can('delete', $moved))->toBeFalse()
        ->and($this->admin->can('delete', $this->product->implicitVariant))->toBeFalse()
        ->and($this->seller->can('delete', $free))->toBeFalse();
});

// --- Tabla de variantes en el producto ------------------------------------

it('la tabla de variantes solo existe con el módulo encendido', function () {
    $this->actingAs($this->admin);

    expect(VariantsRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeTrue();

    Modules::disable(Module::Variants);

    expect(VariantsRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeFalse();
});

it('lista las variantes con nombre y esconde la implícita', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);
    $this->actingAs($this->admin);

    $named = $this->product->variants()->where('name', '!=', '')->get();
    $implicit = $this->product->implicitVariant;

    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->assertCanSeeTableRecords($named)
        ->assertCanNotSeeTableRecords([$implicit]);
});

it('genera variantes desde la tabla del producto', function () {
    $this->actingAs($this->admin);

    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('generate')->table(), [
            'attribute_0' => ['S', 'M'],
            'attribute_1' => ['Negro'],
        ])
        ->assertNotified('Se crearon 2 variantes');

    expect($this->product->variants()->where('name', '!=', '')->pluck('name')->all())->toBe(['S / Negro', 'M / Negro']);
});

it('edita el SKU y el precio propio de una variante desde la tabla', function () {
    generate($this->product, ['Talle' => ['S']]);
    $variant = $this->product->variants()->where('name', 'S')->firstOrFail();
    $this->actingAs($this->admin);

    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('edit')->table($variant), ['name' => 'S', 'sku' => 'REM-S', 'price' => '1200.00', 'active' => true])
        ->assertHasNoFormErrors();

    $variant->refresh();

    expect($variant->sku)->toBe('REM-S')
        ->and($variant->price)->toBe('1200.00')
        ->and($variant->effectivePrice())->toBe('1200.00');
});

it('el SKU de la variante es único', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);
    [$s, $m] = $this->product->variants()->where('name', '!=', '')->get()->all();
    $s->update(['sku' => 'DUP']);
    $this->actingAs($this->admin);

    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->callAction(TestAction::make('edit')->table($m), ['name' => 'M', 'sku' => 'DUP', 'active' => true])
        ->assertHasFormErrors(['sku' => 'unique']);
});

it('borrar una variante solo se ofrece si no tiene historia', function () {
    generate($this->product, ['Talle' => ['S', 'M']]);
    [$free, $sold] = $this->product->variants()->where('name', '!=', '')->get()->all();
    sellVariant($this->admin, $sold);
    $this->actingAs($this->admin);

    Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->assertActionVisible(TestAction::make('delete')->table($free))
        ->assertActionHidden(TestAction::make('delete')->table($sold));
});

it('la variante vendida deja el nombre completo en el ítem', function () {
    generate($this->product, ['Talle' => ['M'], 'Color' => ['Negro']]);
    $variant = $this->product->variants()->where('name', 'M / Negro')->firstOrFail();
    $variant->update(['price' => '1300.00']);

    $sale = sellVariant($this->admin, $variant);

    expect($sale->items->first()->description)->toBe('Remera lisa — M / Negro')
        ->and($sale->items->first()->unit_price)->toBe('1300.00');
});

it('con el módulo apagado la pantalla del producto no incluye la tabla de variantes', function () {
    Modules::disable(Module::Variants);
    $this->actingAs($this->admin);

    $this->get('http://'.config('domains.panel').'/products/'.$this->product->id.'/edit')
        ->assertOk()
        ->assertDontSee('Generar variantes');
});
