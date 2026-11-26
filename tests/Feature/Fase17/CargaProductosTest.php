<?php

use App\Actions\Products\CreateProduct;
use App\Enums\Module;
use App\Enums\StockMovementType;
use App\Facades\Modules;
use App\Filament\Pages\BarcodeLabels;
use App\Filament\Resources\Products\Pages\CreateProduct as CreateProductPage;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();

    Modules::enable(Module::Variants);
    Modules::enable(Module::Stock);
    Modules::updateSettings(Module::Variants, ['attributes' => [
        ['name' => 'Talle', 'values' => ['S', 'M', 'L']],
        ['name' => 'Color', 'values' => ['Negro', 'Blanco']],
    ]]);

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->data = ['name' => 'Remera lisa', 'price' => '1000.00', 'cost' => '600.00'];
});

it('crea las variantes elegidas con su stock inicial en un solo paso', function () {
    $product = app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S', 'Color' => 'Negro'], 'price' => '1200.00', 'initial_stock' => 3, 'min_stock' => 1],
        ['attributes' => ['Talle' => 'M', 'Color' => 'Negro'], 'price' => null, 'initial_stock' => 5, 'min_stock' => null],
    ]]);

    $named = $product->variants()->where('name', '!=', '')->orderBy('position')->get();

    expect($product->variants)->toHaveCount(3)
        ->and($named->pluck('name')->all())->toBe(['S / Negro', 'M / Negro'])
        ->and($named->first()->getAttribute('attributes'))->toBe(['Talle' => 'S', 'Color' => 'Negro'])
        ->and($named->first()->price)->toBe('1200.00')
        ->and($named->last()->price)->toBeNull()
        ->and($named->first()->min_stock)->toBe(1)
        ->and($named->pluck('stock')->all())->toBe([3, 5])
        ->and($named->every(fn ($variant) => filled($variant->sku)))->toBeTrue();
});

it('el precio propio y el stock mínimo son opcionales: sin pedirlos se descartan', function () {
    $product = app(CreateProduct::class)->handle([...$this->data, 'custom_prices' => false, 'track_min_stock' => false, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'price' => '1200.00', 'initial_stock' => 3, 'min_stock' => 2],
    ]]);

    $variant = $product->variants()->where('name', 'S')->firstOrFail();

    expect($variant->price)->toBeNull()
        ->and($variant->min_stock)->toBe(0)
        ->and($variant->stock)->toBe(3);
});

it('pidiéndolos, el precio propio y el stock mínimo se guardan', function () {
    $product = app(CreateProduct::class)->handle([...$this->data, 'custom_prices' => true, 'track_min_stock' => true, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'price' => '1200.00', 'initial_stock' => 3, 'min_stock' => 2],
    ]]);

    $variant = $product->variants()->where('name', 'S')->firstOrFail();

    expect($variant->price)->toBe('1200.00')
        ->and($variant->min_stock)->toBe(2);
});

it('el stock mínimo del producto simple solo se guarda si se pide', function () {
    $without = app(CreateProduct::class)->handle([...$this->data, 'track_min_stock' => false, 'initial_stock' => 5, 'min_stock' => 2]);
    $with = app(CreateProduct::class)->handle([...$this->data, 'name' => 'Con mínimo', 'track_min_stock' => true, 'initial_stock' => 5, 'min_stock' => 2]);

    expect($without->implicitVariant->min_stock)->toBe(0)
        ->and($without->implicitVariant->stock)->toBe(5)
        ->and($with->implicitVariant->min_stock)->toBe(2);
});

it('la grilla suma las columnas «Precio propio» y «Mínimo» solo cuando se piden', function () {
    $page = Livewire::test(CreateProductPage::class)->set('data.attribute_0', ['S', 'M']);

    $page->assertSee('Stock inicial')
        ->assertDontSee('Precio propio')
        ->assertDontSee('Mínimo');

    $page->set('data.custom_prices', true)
        ->assertSee('Precio propio')
        ->assertDontSee('Mínimo');

    $page->set('data.track_min_stock', true)
        ->assertSee('Precio propio')
        ->assertSee('Mínimo');
});

it('el alta desde el panel guarda precio y mínimo solo con las casillas encendidas', function () {
    $row = ['attributes' => ['Talle' => 'S'], 'label' => 'S', 'price' => '1500.00', 'initial_stock' => 2, 'min_stock' => 1];

    Livewire::test(CreateProductPage::class)
        ->fillForm([...$this->data, 'name' => 'Sin extras', 'variants' => [$row]])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateProductPage::class)
        ->fillForm([...$this->data, 'name' => 'Con extras', 'custom_prices' => true, 'track_min_stock' => true, 'variants' => [$row]])
        ->call('create')
        ->assertHasNoFormErrors();

    $plain = Product::query()->where('name', 'Sin extras')->firstOrFail()->variants()->where('name', 'S')->firstOrFail();
    $extra = Product::query()->where('name', 'Con extras')->firstOrFail()->variants()->where('name', 'S')->firstOrFail();

    expect($plain->price)->toBeNull()
        ->and($plain->min_stock)->toBe(0)
        ->and($extra->price)->toBe('1500.00')
        ->and($extra->min_stock)->toBe(1);
});

it('deja el stock inicial como un movimiento «Carga inicial» a nombre de quien lo carga', function () {
    $product = app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'initial_stock' => 4],
    ]]);

    $variant = $product->variants()->where('name', 'S')->firstOrFail();
    $movement = StockMovement::query()->where('product_variant_id', $variant->id)->sole();

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(4)
        ->and($movement->balance)->toBe(4)
        ->and($movement->reason)->toBe('Carga inicial')
        ->and($movement->user_id)->toBe($this->admin->id);
});

it('no registra movimientos con stock vacío o en cero', function () {
    app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'initial_stock' => 0],
        ['attributes' => ['Talle' => 'M'], 'initial_stock' => null],
    ]]);

    expect(StockMovement::query()->count())->toBe(0);
});

it('un producto simple guarda su stock inicial y mínimo en la variante implícita', function () {
    $product = app(CreateProduct::class)->handle([...$this->data, 'initial_stock' => 7, 'min_stock' => 2]);

    $implicit = $product->implicitVariant;

    expect($product->variants)->toHaveCount(1)
        ->and($implicit->stock)->toBe(7)
        ->and($implicit->min_stock)->toBe(2)
        ->and(StockMovement::query()->where('product_variant_id', $implicit->id)->value('reason'))->toBe('Carga inicial');
});

it('con el módulo Stock apagado no registra movimientos', function () {
    Modules::disable(Module::Stock);

    $product = app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'initial_stock' => 4],
    ]]);

    expect($product->variants)->toHaveCount(2)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and($product->variants()->where('name', 'S')->value('stock'))->toBe(0);
});

it('con el módulo Variantes apagado ignora las variantes y crea solo el producto', function () {
    Modules::disable(Module::Variants);

    $product = app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S'], 'initial_stock' => 4],
    ]]);

    expect($product->variants)->toHaveCount(1);
});

it('rechaza valores que no son de los atributos, repetidos o cantidades inválidas', function (array $row) {
    expect(fn () => app(CreateProduct::class)->handle([...$this->data, 'variants' => [$row]]))
        ->toThrow(ValidationException::class);

    expect(Product::query()->count())->toBe(0);
})->with([
    'valor inexistente' => [['attributes' => ['Talle' => 'XXL']]],
    'atributo inexistente' => [['attributes' => ['Material' => 'Lana']]],
    'sin atributos' => [['attributes' => []]],
    'stock negativo' => [['attributes' => ['Talle' => 'S'], 'initial_stock' => -1]],
    'stock con decimales' => [['attributes' => ['Talle' => 'S'], 'initial_stock' => '1.5']],
    'precio negativo' => [['attributes' => ['Talle' => 'S'], 'price' => '-5']],
]);

it('rechaza la misma combinación dos veces', function () {
    expect(fn () => app(CreateProduct::class)->handle([...$this->data, 'variants' => [
        ['attributes' => ['Talle' => 'S']],
        ['attributes' => ['Talle' => 'S']],
    ]]))->toThrow(ValidationException::class);

    expect(Product::query()->count())->toBe(0);
});

it('arma la grilla al elegir atributos y conserva lo ya escrito al cambiar la selección', function () {
    $page = Livewire::test(CreateProductPage::class)
        ->set('data.attribute_0', ['S', 'M']);

    expect($page->get('data.variants'))->toHaveCount(2);

    $first = array_key_first($page->get('data.variants'));

    $page->set("data.variants.{$first}.initial_stock", 9)
        ->set('data.attribute_0', ['S', 'M', 'L']);

    $rows = collect($page->get('data.variants'));

    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('label', 'S')['initial_stock'])->toBe(9);
});

it('el alta desde el panel crea el producto con sus variantes y stock', function () {
    Livewire::test(CreateProductPage::class)
        ->fillForm([
            ...$this->data,
            'variants' => [
                ['attributes' => ['Talle' => 'S'], 'label' => 'S', 'price' => null, 'initial_stock' => 2, 'min_stock' => null],
                ['attributes' => ['Talle' => 'M'], 'label' => 'M', 'price' => null, 'initial_stock' => 6, 'min_stock' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('name', 'Remera lisa')->firstOrFail();

    expect($product->variants()->where('name', '!=', '')->orderBy('position')->pluck('stock', 'name')->all())
        ->toBe(['S' => 2, 'M' => 6]);
});

describe('etiquetas al crear', function () {
    beforeEach(function () {
        Modules::enable(Module::Barcodes);

        $this->variants = [
            ['attributes' => ['Talle' => 'S'], 'label' => 'S', 'price' => null, 'initial_stock' => 3, 'min_stock' => null],
            ['attributes' => ['Talle' => 'M'], 'label' => 'M', 'price' => null, 'initial_stock' => 0, 'min_stock' => null],
        ];
    });

    it('con «ofrecer» y la casilla marcada lleva a Etiquetas, una por unidad de stock (mínimo una)', function () {
        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'variants' => $this->variants, 'print_labels' => true])
            ->call('create');

        $product = Product::query()->where('name', 'Remera lisa')->firstOrFail();
        $selection = BarcodeLabels::selectionForNewProduct($product);

        expect(array_values($selection))->toBe([3, 1]);

        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'name' => 'Otra', 'variants' => $this->variants, 'print_labels' => true])
            ->call('create')
            ->assertRedirect(BarcodeLabels::urlFor(
                BarcodeLabels::selectionForNewProduct(Product::query()->where('name', 'Otra')->firstOrFail()),
            ));
    });

    it('con la casilla desmarcada sigue el redirect normal', function () {
        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'variants' => $this->variants, 'print_labels' => false])
            ->call('create')
            ->assertRedirectContains('/products');
    });

    it('con «no ofrecer» nunca va a Etiquetas', function () {
        Modules::updateSettings(Module::Barcodes, ['labels_on_create' => 'off']);

        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'variants' => $this->variants, 'print_labels' => true])
            ->call('create')
            ->assertRedirectContains('/products');
    });

    it('con «siempre» va a Etiquetas sin necesidad de la casilla', function () {
        Modules::updateSettings(Module::Barcodes, ['labels_on_create' => 'always']);

        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'variants' => $this->variants, 'print_labels' => false])
            ->call('create')
            ->assertRedirectContains('barcode-labels');
    });

    it('con el módulo apagado sigue el redirect normal', function () {
        Modules::disable(Module::Barcodes);

        Livewire::test(CreateProductPage::class)
            ->fillForm([...$this->data, 'variants' => $this->variants])
            ->call('create')
            ->assertRedirectContains('/products');
    });

    it('con cantidad «una por variante» imprime una de cada una', function () {
        Modules::updateSettings(Module::Barcodes, ['labels_on_create_quantity' => 'one']);

        $product = app(CreateProduct::class)->handle([...$this->data, 'variants' => [
            ['attributes' => ['Talle' => 'S'], 'initial_stock' => 5],
            ['attributes' => ['Talle' => 'M'], 'initial_stock' => 8],
        ]]);

        expect(array_values(BarcodeLabels::selectionForNewProduct($product)))->toBe([1, 1]);
    });
});
