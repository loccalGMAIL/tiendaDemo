<?php

use App\Actions\Products\UpdateProduct;
use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

// --- Slug -----------------------------------------------------------------

it('todo producto recibe un slug a partir del nombre, único y estable', function () {
    $first = Product::factory()->create(['name' => 'Remera lisa']);
    $second = Product::factory()->create(['name' => 'Remera lisa']);
    $accents = Product::factory()->create(['name' => 'Camperón Ñandú 100%']);

    expect($first->slug)->toBe('remera-lisa')
        ->and($second->slug)->toBe('remera-lisa-2')
        ->and($accents->slug)->toBe('camperon-nandu-100');

    // Renombrar no cambia el enlace público.
    $first->update(['name' => 'Remera lisa de verano']);

    expect($first->fresh()->slug)->toBe('remera-lisa');
});

it('un nombre sin letras ni números usa un slug por defecto', function () {
    expect(Product::factory()->create(['name' => '***'])->slug)->toBe('producto');
});

it('el slug se puede indicar a mano y se conserva', function () {
    $product = Product::factory()->create(['name' => 'Remera', 'slug' => 'mi-remera']);

    expect($product->slug)->toBe('mi-remera');
});

// --- Pestaña Catálogo -----------------------------------------------------

it('la pestaña Catálogo solo existe con el módulo encendido', function () {
    $product = Product::factory()->create();

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertFormFieldExists('name')
        ->assertDontSee('Publicado en el catálogo');

    Modules::enable(Module::Catalog);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->assertSee('Publicado en el catálogo')
        ->assertSee('Destacado en la landing')
        ->assertSee('Mostrar el precio')
        ->assertSee('Imágenes');
});

it('crea un producto con sus datos de catálogo', function () {
    Modules::enable(Module::Catalog);

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Remera lisa',
            'price' => '1500.00',
            'cost' => '900.00',
            'is_published' => true,
            'is_featured' => true,
            'show_price' => false,
            'slug' => 'remera-de-verano',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    expect($product->is_published)->toBeTrue()
        ->and($product->is_featured)->toBeTrue()
        ->and($product->show_price)->toBeFalse()
        ->and($product->slug)->toBe('remera-de-verano')
        ->and($product->variants)->toHaveCount(1);
});

it('por defecto un producto nuevo no está publicado y muestra su precio', function () {
    Modules::enable(Module::Catalog);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Gorra', 'price' => '100.00', 'cost' => '50.00'])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::firstOrFail();

    expect($product->is_published)->toBeFalse()
        ->and($product->is_featured)->toBeFalse()
        ->and($product->show_price)->toBeTrue()
        ->and($product->slug)->toBe('gorra');
});

it('publica y destaca desde la edición', function () {
    Modules::enable(Module::Catalog);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['is_published' => true, 'is_featured' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->is_published)->toBeTrue()->and($product->fresh()->is_featured)->toBeTrue();
});

it('editar con el slug vacío conserva el actual', function () {
    Modules::enable(Module::Catalog);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['slug' => '', 'name' => 'Remera nueva'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->slug)->toBe('remera')->and($product->fresh()->name)->toBe('Remera nueva');
});

it('el slug editado a mano tiene que ser único y con formato válido', function () {
    Modules::enable(Module::Catalog);
    Product::factory()->create(['name' => 'Otra', 'slug' => 'ocupado']);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['slug' => 'ocupado'])
        ->call('save')
        ->assertHasFormErrors(['slug' => 'unique']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['slug' => 'Con Mayúsculas y espacios'])
        ->call('save')
        ->assertHasFormErrors(['slug']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['slug' => 'nuevo-enlace'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->slug)->toBe('nuevo-enlace');
});

// --- Imágenes -------------------------------------------------------------

it('sube las imágenes al disco público', function () {
    Modules::enable(Module::Catalog);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['images' => [UploadedFile::fake()->image('uno.jpg'), UploadedFile::fake()->image('dos.png')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $paths = $product->fresh()->imagePaths();

    expect($paths)->toHaveCount(2);

    foreach ($paths as $path) {
        expect($path)->toStartWith('products/');
        Storage::disk('public')->assertExists($path);
    }

    expect($product->fresh()->imageUrls())->toHaveCount(2);
});

it('no acepta archivos que no son imágenes ni más de seis', function () {
    Modules::enable(Module::Catalog);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['images' => [UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')]])
        ->call('save')
        ->assertHasFormErrors(['images']);

    $seven = array_map(fn (int $i) => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 7));

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['images' => $seven])
        ->call('save')
        ->assertHasFormErrors(['images']);

    expect($product->fresh()->imagePaths())->toBe([]);
});

it('quitar una imagen la borra del disco', function () {
    Modules::enable(Module::Catalog);
    Storage::disk('public')->put('products/a.jpg', 'a');
    Storage::disk('public')->put('products/b.jpg', 'b');
    $product = Product::factory()->create(['images' => ['products/a.jpg', 'products/b.jpg']]);

    app(UpdateProduct::class)->handle($product, ['images' => ['products/a.jpg']]);

    Storage::disk('public')->assertExists('products/a.jpg');
    Storage::disk('public')->assertMissing('products/b.jpg');
    expect($product->fresh()->imagePaths())->toBe(['products/a.jpg']);

    app(UpdateProduct::class)->handle($product, ['images' => []]);

    Storage::disk('public')->assertMissing('products/a.jpg');
});

it('editar sin tocar las imágenes no las borra', function () {
    Storage::disk('public')->put('products/a.jpg', 'a');
    $product = Product::factory()->create(['images' => ['products/a.jpg']]);

    app(UpdateProduct::class)->handle($product, ['name' => 'Otro nombre']);

    Storage::disk('public')->assertExists('products/a.jpg');
});

it('borrar el producto borra sus imágenes', function () {
    Storage::disk('public')->put('products/a.jpg', 'a');
    Storage::disk('public')->put('products/b.jpg', 'b');
    $product = Product::factory()->create(['images' => ['products/a.jpg', 'products/b.jpg']]);

    $product->delete();

    Storage::disk('public')->assertMissing('products/a.jpg');
    Storage::disk('public')->assertMissing('products/b.jpg');
});

// --- Lista ----------------------------------------------------------------

it('la lista de productos muestra y filtra «Publicado» solo con el módulo', function () {
    $published = Product::factory()->create(['is_published' => true]);
    $draft = Product::factory()->create(['is_published' => false]);

    Livewire::test(ListProducts::class)->assertTableColumnHidden('is_published');

    Modules::enable(Module::Catalog);

    Livewire::test(ListProducts::class)
        ->assertTableColumnVisible('is_published')
        ->assertCanSeeTableRecords([$published, $draft])
        ->filterTable('is_published', true)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('los datos del catálogo quedan en la actividad del producto', function () {
    Modules::enable(Module::Catalog);
    $product = Product::factory()->create(['name' => 'Remera']);

    Livewire::test(EditProduct::class, ['record' => $product->id])
        ->fillForm(['is_published' => true])
        ->call('save');

    $log = ActivityLog::where('subject_id', $product->id)
        ->where('subject_type', $product->getMorphClass())
        ->where('event', ActivityEvent::Updated)
        ->latest('id')
        ->firstOrFail();

    expect($log->properties['new'])->toHaveKey('is_published')
        ->and($log->properties['old']['is_published'])->toBe(0);
});
