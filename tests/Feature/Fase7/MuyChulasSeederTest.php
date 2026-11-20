<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\SiteContent;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MuyChulasSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    resetModules();
    Storage::fake('public');
    config(['platform.admin.email' => 'admin@cliente.test', 'platform.admin.password' => 'secreto-123']);
    $this->seed(DatabaseSeeder::class);
});

it('carga el sitio, las categorías y los productos de Muy Chulas', function () {
    Modules::enableAll();
    $this->seed(MuyChulasSeeder::class);

    $site = app(SiteContent::class);

    expect($site->name())->toBe('Muy Chulas')
        ->and($site->whatsappUrl())->toBe('https://wa.me/5493442660818')
        ->and(Category::count())->toBe(9)
        ->and(Product::count())->toBe(31)
        ->and(Product::where('is_published', true)->count())->toBe(31)
        ->and(Product::where('is_featured', true)->count())->toBe(6)
        ->and(Product::where('name', 'Bikini')->value('price'))->toBe('30000.00')
        ->and(Sale::count())->toBe(0);
});

it('genera las variantes por talle y color y les carga stock', function () {
    Modules::enableAll();
    $this->seed(MuyChulasSeeder::class);

    $basica = Product::where('name', 'Remera básica')->firstOrFail();
    $blazer = Product::where('name', 'Blazer')->firstOrFail();

    expect($basica->variants()->where('name', '!=', '')->count())->toBe(16)
        ->and($basica->variants()->where('name', 'M / Negro')->value('stock'))->toBe(6)
        ->and($blazer->implicitVariant->stock)->toBe(2)
        ->and(ProductVariant::where('name', '!=', '')->where('stock', 0)->count())->toBe(0)
        ->and(StockMovement::where('reason', 'Stock inicial')->count())->toBeGreaterThan(0);
});

it('suma sus talles y colores a los atributos ya configurados sin quitar ninguno', function () {
    Modules::enable(Module::Variants);
    Modules::updateSettings(Module::Variants, ['attributes' => [
        ['name' => 'Talle', 'values' => ['S', 'M', 'L', 'XL']],
        ['name' => 'Color', 'values' => ['Negro', 'Azul']],
    ]]);

    $this->seed(MuyChulasSeeder::class);

    $attributes = collect(Modules::setting(Module::Variants, 'attributes'))->pluck('values', 'name');

    expect($attributes['Talle'])->toContain('S', 'XXL', '5', '38/40')
        ->and($attributes['Color'])->toContain('Azul', 'Borravino')
        ->and(array_slice($attributes['Color'], 0, 2))->toBe(['Negro', 'Azul']);
});

it('cada producto queda con su foto en el catálogo', function () {
    Modules::enableAll();
    $this->seed(MuyChulasSeeder::class);

    foreach (Product::all() as $product) {
        expect($product->imagePaths())->toHaveCount(1);
        Storage::disk('public')->assertExists($product->imagePaths()[0]);
    }

    $this->get('http://'.config('domains.landing').'/catalogo')->assertOk()->assertSee('Bomber gamuzada');
});

it('con solo el núcleo carga los productos sin variantes, stock ni catálogo', function () {
    $this->seed(MuyChulasSeeder::class);

    expect(Product::count())->toBe(31)
        ->and(ProductVariant::where('name', '!=', '')->count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(Product::where('is_published', true)->count())->toBe(0)
        ->and(Product::whereNotNull('images')->count())->toBe(0);
});

it('si ya hay productos no agrega nada', function () {
    Modules::enableAll();
    Product::factory()->create();

    $this->seed(MuyChulasSeeder::class);

    expect(Product::count())->toBe(1)->and(Category::where('name', 'Bikinis')->exists())->toBeFalse();
});

it('en producción carga los productos', function () {
    Modules::enableAll();
    $this->app['env'] = 'production';

    app(MuyChulasSeeder::class)->run();

    expect(Product::count())->toBeGreaterThan(0);
});
