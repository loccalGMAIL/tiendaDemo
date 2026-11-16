<?php

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

it('asigna un SKU automático y secuencial cuando viene vacío', function () {
    $first = app(CreateProduct::class)->handle(['name' => 'Remera', 'price' => '1000.00', 'cost' => '600.00']);
    $second = app(CreateProduct::class)->handle(['name' => 'Pantalón', 'sku' => '', 'price' => '2000.00', 'cost' => '1200.00']);

    expect($first->sku)->toBe('PROD-00001')
        ->and($second->sku)->toBe('PROD-00002');
});

it('respeta el SKU cargado a mano', function () {
    $product = app(CreateProduct::class)->handle(['name' => 'Remera', 'sku' => 'REM-001', 'price' => '1000.00', 'cost' => '0']);

    expect($product->sku)->toBe('REM-001');
});

it('el SKU automático no choca con uno cargado a mano', function () {
    app(CreateProduct::class)->handle(['name' => 'Manual', 'sku' => 'PROD-00002', 'price' => '1.00', 'cost' => '0']);

    $auto = app(CreateProduct::class)->handle(['name' => 'Automático', 'price' => '1.00', 'cost' => '0']);

    expect($auto->sku)->toBe('PROD-00003');
});

it('todo producto nace con su variante implícita', function () {
    $product = app(CreateProduct::class)->handle(['name' => 'Remera', 'price' => '1000.00', 'cost' => '600.00']);

    $variants = $product->variants;

    expect($variants)->toHaveCount(1)
        ->and($variants->first()->name)->toBe('')
        ->and($variants->first()->isImplicit())->toBeTrue()
        ->and($variants->first()->active)->toBeTrue()
        ->and($product->implicitVariant->is($variants->first()))->toBeTrue();
});

it('la fábrica también deja al producto con su variante implícita', function () {
    $product = Product::factory()->create();

    expect($product->variants()->count())->toBe(1)
        ->and($product->implicitVariant)->not->toBeNull();
});

it('la variante sin precio propio hereda el del producto', function () {
    $product = Product::factory()->priced('1500.00')->create();
    $implicit = $product->implicitVariant;

    expect($implicit->price)->toBeNull()
        ->and($implicit->effectivePrice())->toBe('1500.00')
        ->and($product->effectivePrice($implicit))->toBe('1500.00');

    $own = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'M / Negro', 'price' => '1800.00']);

    expect($own->effectivePrice())->toBe('1800.00')
        ->and($product->effectivePrice($own))->toBe('1800.00');
});

it('arma el nombre completo de la variante', function () {
    $product = Product::factory()->create(['name' => 'Remera lisa']);
    $named = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'M / Negro']);

    expect($product->implicitVariant->fullName())->toBe('Remera lisa')
        ->and($named->fullName())->toBe('Remera lisa — M / Negro');
});

it('al editar, un SKU vacío conserva el que ya tenía', function () {
    $product = app(CreateProduct::class)->handle(['name' => 'Remera', 'price' => '1000.00', 'cost' => '600.00']);

    app(UpdateProduct::class)->handle($product, ['name' => 'Remera lisa', 'sku' => '', 'price' => '1100.00']);

    expect($product->fresh()->sku)->toBe('PROD-00001')
        ->and($product->fresh()->name)->toBe('Remera lisa')
        ->and($product->fresh()->price)->toBe('1100.00');
});

it('los montos del producto se guardan con dos decimales exactos', function () {
    $product = Product::factory()->create(['price' => '1234.5', 'cost' => '0.1']);

    expect($product->fresh()->price)->toBe('1234.50')
        ->and($product->fresh()->cost)->toBe('0.10');
});

it('la categoría arma su slug solo y es único', function () {
    $first = Category::create(['name' => 'Remeras de verano']);
    $second = Category::create(['name' => 'Remeras de verano']);
    $manual = Category::create(['name' => 'Otra', 'slug' => 'mi-slug']);

    expect($first->slug)->toBe('remeras-de-verano')
        ->and($second->slug)->toBe('remeras-de-verano-2')
        ->and($manual->slug)->toBe('mi-slug');
});

it('borrar una categoría deja sus productos sin categoría', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $category->delete();

    expect($product->fresh()->category_id)->toBeNull();
});
