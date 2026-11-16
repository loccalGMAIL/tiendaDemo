<?php

use App\Actions\Stock\AdjustStock;
use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Appearance;
use App\Services\SiteContent;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    resetModules();
    Modules::enable(Module::Catalog);
    Storage::fake('public');
    $this->withoutVite();

    $this->base = 'http://'.config('domains.landing');
    $this->admin = User::factory()->admin()->create();
});

function shopProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge(['is_published' => true], $attributes));
}

function shopSite(array $content): void
{
    $site = app(SiteContent::class);
    $site->save(array_merge($site->all(), $content));
}

// --- Visibilidad ----------------------------------------------------------

it('el catálogo lista solo productos activos y publicados', function () {
    shopProduct(['name' => 'Remera visible']);
    shopProduct(['name' => 'Gorra visible']);
    shopProduct(['name' => 'Pantalón sin publicar', 'is_published' => false]);
    shopProduct(['name' => 'Campera inactiva', 'active' => false]);

    $this->get($this->base.'/catalogo')
        ->assertOk()
        ->assertSee('Remera visible')
        ->assertSee('Gorra visible')
        ->assertDontSee('Pantalón sin publicar')
        ->assertDontSee('Campera inactiva');
});

it('la ficha solo existe para productos activos y publicados', function () {
    $ok = shopProduct(['name' => 'Remera visible']);
    $draft = shopProduct(['name' => 'Borrador', 'is_published' => false]);
    $inactive = shopProduct(['name' => 'Inactivo', 'active' => false]);

    $this->get($this->base.'/producto/'.$ok->slug)->assertOk()->assertSee('Remera visible');
    $this->get($this->base.'/producto/'.$draft->slug)->assertNotFound();
    $this->get($this->base.'/producto/'.$inactive->slug)->assertNotFound();
    $this->get($this->base.'/producto/no-existe')->assertNotFound();
});

it('la ficha responde 404 si el slug tiene caracteres inválidos', function () {
    $this->get($this->base.'/producto/Remera%20Lisa')->assertNotFound();
});

it('la categoría lista solo sus productos publicados', function () {
    $remeras = Category::factory()->create(['name' => 'Remeras']);
    $gorras = Category::factory()->create(['name' => 'Gorras']);
    shopProduct(['name' => 'Remera lisa', 'category_id' => $remeras->id]);
    shopProduct(['name' => 'Remera oculta', 'category_id' => $remeras->id, 'is_published' => false]);
    shopProduct(['name' => 'Gorra negra', 'category_id' => $gorras->id]);

    $this->get($this->base.'/catalogo/'.$remeras->slug)
        ->assertOk()
        ->assertSee('Remera lisa')
        ->assertDontSee('Remera oculta')
        ->assertDontSee('Gorra negra');
});

it('una categoría inactiva o inexistente responde 404', function () {
    $inactive = Category::factory()->create(['name' => 'Vieja', 'active' => false]);

    $this->get($this->base.'/catalogo/'.$inactive->slug)->assertNotFound();
    $this->get($this->base.'/catalogo/no-existe')->assertNotFound();
});

it('con el módulo apagado todas las rutas públicas responden 404 y no queda rastro en la landing', function () {
    $category = Category::factory()->create(['name' => 'Remeras']);
    $product = shopProduct(['name' => 'Remera lisa', 'category_id' => $category->id, 'is_featured' => true]);

    $this->get($this->base.'/')->assertSee('id="catalog"', false)->assertSee('/catalogo');

    Modules::disable(Module::Catalog);

    $this->get($this->base.'/catalogo')->assertNotFound();
    $this->get($this->base.'/catalogo/'.$category->slug)->assertNotFound();
    $this->get($this->base.'/producto/'.$product->slug)->assertNotFound();

    $this->get($this->base.'/')
        ->assertOk()
        ->assertDontSee('id="catalog"', false)
        ->assertDontSee('/catalogo')
        ->assertDontSee('Remera lisa');
});

it('un host desconocido no sirve el catálogo', function () {
    shopProduct();

    $this->get('http://desconocido.localhost/catalogo')->assertNotFound();
});

// --- Precio ---------------------------------------------------------------

it('muestra el precio o «Consultar precio» según el producto', function () {
    $visible = shopProduct(['name' => 'Con precio', 'price' => '1234.50']);
    $hidden = shopProduct(['name' => 'Sin precio', 'price' => '7777.77', 'show_price' => false]);

    $this->get($this->base.'/catalogo')
        ->assertSee('$ 1.234,50')
        ->assertSee('Consultar precio')
        ->assertDontSee('7.777,77')
        ->assertDontSee('7777.77');

    $this->get($this->base.'/producto/'.$visible->slug)->assertSee('$ 1.234,50');

    $this->get($this->base.'/producto/'.$hidden->slug)
        ->assertSee('Consultar precio')
        ->assertDontSee('7.777,77')
        ->assertDontSee('7777.77');
});

it('con variantes de precios distintos muestra «Desde» el más bajo', function () {
    Modules::enable(Module::Variants);
    $product = shopProduct(['price' => '1000.00']);
    $product->variants()->create(['name' => 'M', 'price' => '1500.00', 'position' => 1]);
    $product->variants()->create(['name' => 'L', 'price' => '1200.00', 'position' => 2]);
    $product->variants()->create(['name' => 'XL', 'price' => '900.00', 'position' => 3, 'active' => false]);

    $this->get($this->base.'/catalogo')->assertSee('Desde $ 1.200,00');
    $this->get($this->base.'/producto/'.$product->slug)->assertSee('Desde $ 1.200,00');
});

it('con variantes del mismo precio muestra ese precio sin «Desde»', function () {
    Modules::enable(Module::Variants);
    $product = shopProduct(['price' => '1000.00']);
    $product->variants()->create(['name' => 'M', 'position' => 1]);
    $product->variants()->create(['name' => 'L', 'position' => 2]);

    $this->get($this->base.'/producto/'.$product->slug)->assertSee('$ 1.000,00')->assertDontSee('Desde');
});

// --- Buscador -------------------------------------------------------------

it('busca por nombre, marca, descripción y categoría', function () {
    $abrigos = Category::factory()->create(['name' => 'Abrigos']);
    shopProduct(['name' => 'Remera lisa', 'brand' => 'Basic']);
    shopProduct(['name' => 'Gorra', 'brand' => 'Nube', 'description' => 'Visera curva de algodón']);
    shopProduct(['name' => 'Campera', 'category_id' => $abrigos->id]);

    $this->get($this->base.'/catalogo?q=remera')->assertSee('Remera lisa')->assertDontSee('Gorra');
    $this->get($this->base.'/catalogo?q=nube')->assertSee('Gorra')->assertDontSee('Remera lisa');
    $this->get($this->base.'/catalogo?q=visera')->assertSee('Gorra')->assertDontSee('Campera');
    $this->get($this->base.'/catalogo?q=abrigos')->assertSee('Campera')->assertDontSee('Remera lisa');
});

it('la búsqueda respeta lo no publicado', function () {
    shopProduct(['name' => 'Remera pública']);
    shopProduct(['name' => 'Remera borrador', 'is_published' => false]);

    $this->get($this->base.'/catalogo?q=remera')->assertSee('Remera pública')->assertDontSee('Remera borrador');
});

it('busca dentro de una categoría', function () {
    $remeras = Category::factory()->create(['name' => 'Remeras']);
    shopProduct(['name' => 'Remera azul', 'category_id' => $remeras->id]);
    shopProduct(['name' => 'Remera roja', 'category_id' => $remeras->id]);
    shopProduct(['name' => 'Remera suelta']);

    $this->get($this->base.'/catalogo/'.$remeras->slug.'?q=azul')
        ->assertSee('Remera azul')
        ->assertDontSee('Remera roja')
        ->assertDontSee('Remera suelta');
});

it('los comodines escritos en la búsqueda se tratan como texto', function () {
    shopProduct(['name' => 'Remera 100% algodón']);
    shopProduct(['name' => 'Remera cien algodón']);
    shopProduct(['name' => 'Cinturón a_b']);
    shopProduct(['name' => 'Cinturón axb']);

    $this->get($this->base.'/catalogo?q='.urlencode('100%'))->assertSee('100% algodón')->assertDontSee('cien algodón');
    $this->get($this->base.'/catalogo?q='.urlencode('a_b'))->assertSee('Cinturón a_b')->assertDontSee('Cinturón axb');
    $this->get($this->base.'/catalogo?q='.urlencode('%'))->assertSee('100% algodón')->assertDontSee('Cinturón axb');
});

it('muestra un aviso cuando no hay resultados y conserva lo buscado', function () {
    shopProduct(['name' => 'Remera lisa']);

    $this->get($this->base.'/catalogo?q=inexistente')
        ->assertOk()
        ->assertSee('No encontramos productos')
        ->assertSee('0 resultados para «inexistente»')
        ->assertSee('value="inexistente"', false);
});

it('escapa lo que se escribe en el buscador', function () {
    $this->get($this->base.'/catalogo?q='.urlencode('"><script>alert(1)</script>'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('pagina de a 12 y conserva la búsqueda en los enlaces', function () {
    foreach (range(1, 13) as $i) {
        shopProduct(['name' => sprintf('Remera %02d', $i)]);
    }

    $first = $this->get($this->base.'/catalogo')->assertOk();
    $first->assertSee('Remera 01')->assertSee('Remera 12')->assertDontSee('Remera 13')->assertSee('Página 1 de 2');
    expect($first->getContent())->toContain('page=2');

    $this->get($this->base.'/catalogo?page=2')
        ->assertSee('Remera 13')
        ->assertDontSee('Remera 01')
        ->assertSee('Página 2 de 2')
        ->assertSee('rel="prev"', false);

    $search = $this->get($this->base.'/catalogo?q=remera')->getContent();
    expect($search)->toContain('q=remera')->toContain('page=2');
});

// --- Variantes y stock ----------------------------------------------------

it('la ficha muestra las variantes activas y solo con el módulo Variantes', function () {
    Modules::enable(Module::Variants);
    $product = shopProduct(['name' => 'Remera lisa']);
    $product->variants()->create(['name' => 'M / Negro', 'position' => 1]);
    $product->variants()->create(['name' => 'L / Negro', 'position' => 2]);
    $product->variants()->create(['name' => 'XL / Negro', 'position' => 3, 'active' => false]);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertSee('Opciones')
        ->assertSee('M / Negro')
        ->assertSee('L / Negro')
        ->assertDontSee('XL / Negro');

    Modules::disable(Module::Variants);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertDontSee('Opciones')
        ->assertDontSee('M / Negro');
});

it('con Stock las variantes agotadas se marcan o se ocultan según el ajuste del catálogo', function () {
    Modules::enable(Module::Variants);
    Modules::enable(Module::Stock);
    $product = shopProduct(['name' => 'Remera lisa']);
    $m = $product->variants()->create(['name' => 'M', 'position' => 1]);
    $l = $product->variants()->create(['name' => 'L', 'position' => 2]);
    app(AdjustStock::class)->handle($l, 5, 'Stock inicial', $this->admin);

    $url = $this->base.'/producto/'.$product->slug;

    $this->get($url)->assertSee('M')->assertSee('L')->assertSee('(agotado)');

    Modules::updateSettings(Module::Catalog, ['show_sold_out' => false]);

    $html = $this->get($url)->assertOk()->getContent();
    expect($html)->not->toContain('(agotado)')->toContain('Opciones');
    expect($m->fresh()->stock)->toBe(0);
});

it('un producto totalmente agotado se marca, o no se lista si el catálogo oculta los agotados', function () {
    Modules::enable(Module::Stock);
    $sold = shopProduct(['name' => 'Producto agotado']);
    $available = shopProduct(['name' => 'Producto disponible']);
    app(AdjustStock::class)->handle($available->implicitVariant, 4, 'Stock inicial', $this->admin);

    $this->get($this->base.'/catalogo')->assertSee('Producto agotado')->assertSee('Agotado')->assertSee('Producto disponible');
    $this->get($this->base.'/producto/'.$sold->slug)->assertSee('Agotado');

    Modules::updateSettings(Module::Catalog, ['show_sold_out' => false]);

    $this->get($this->base.'/catalogo')->assertDontSee('Producto agotado')->assertSee('Producto disponible');
    // La ficha sigue accesible por enlace directo y avisa que está agotado.
    $this->get($this->base.'/producto/'.$sold->slug)->assertOk()->assertSee('Agotado');
});

it('sin el módulo Stock no se muestra nada de existencias', function () {
    $product = shopProduct(['name' => 'Sin stock cargado']);

    $this->get($this->base.'/catalogo')->assertSee('Sin stock cargado')->assertDontSee('Agotado');
    $this->get($this->base.'/producto/'.$product->slug)->assertDontSee('Agotado');
});

it('las existencias nunca se muestran, solo el estado', function () {
    Modules::enable(Module::Stock);
    $product = shopProduct(['name' => 'Remera lisa']);
    app(AdjustStock::class)->handle($product->implicitVariant, 37, 'Stock inicial', $this->admin);

    $this->get($this->base.'/producto/'.$product->slug)->assertOk()->assertDontSee('37')->assertDontSee('stock');
    $this->get($this->base.'/catalogo')->assertDontSee('37');
});

// --- Ficha ----------------------------------------------------------------

it('la ficha muestra las imágenes y las usa para compartir', function () {
    Storage::disk('public')->put('products/a.jpg', 'a');
    Storage::disk('public')->put('products/b.jpg', 'b');
    $product = shopProduct(['name' => 'Remera lisa', 'images' => ['products/a.jpg', 'products/b.jpg']]);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertSee('storage/products/a.jpg', false)
        ->assertSee('storage/products/b.jpg', false)
        ->assertSee('<meta property="og:image" content="', false);

    $this->get($this->base.'/catalogo')->assertSee('storage/products/a.jpg', false)->assertDontSee('storage/products/b.jpg', false);
});

it('si no hay imágenes muestra un marcador con la inicial', function () {
    $product = shopProduct(['name' => 'Zapato', 'images' => null]);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertOk()
        ->assertDontSee('storage/products', false)
        ->assertSee('>Z<', false);
});

it('una imagen que ya no está en el disco no se muestra', function () {
    $product = shopProduct(['name' => 'Remera', 'images' => ['products/fantasma.jpg']]);

    $this->get($this->base.'/producto/'.$product->slug)->assertOk()->assertDontSee('fantasma.jpg');
});

it('arma la consulta por WhatsApp con el nombre del producto y su enlace', function () {
    shopSite(['whatsapp' => '+54 9 11 1234-5678']);
    $product = shopProduct(['name' => 'Remera lisa']);

    $html = $this->get($this->base.'/producto/'.$product->slug)->assertSee('Consultar por WhatsApp')->getContent();

    expect($html)->toContain('https://wa.me/5491112345678?text=')
        ->toContain(rawurlencode('Hola, quería consultar por Remera lisa'))
        ->toContain(rawurlencode('/producto/'.$product->slug));
});

it('sin número de WhatsApp no hay botón de consulta', function () {
    $product = shopProduct(['name' => 'Remera lisa']);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertOk()
        ->assertDontSee('Consultar por WhatsApp')
        ->assertDontSee('wa.me');
});

it('el título y la descripción de la ficha salen del producto', function () {
    shopSite(['name' => 'Casa Lila']);
    $product = shopProduct(['name' => 'Remera lisa', 'description' => "Algodón peinado.\nLavar a mano."]);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertSee('<title>Remera lisa — Casa Lila</title>', false)
        ->assertSee('<meta name="description" content="Algodón peinado. Lavar a mano.">', false)
        ->assertSee('Algodón peinado.<br />', false);
});

it('escapa el nombre, la marca y la descripción del producto', function () {
    $product = shopProduct([
        'name' => '<b>Remera</b> <script>alert(1)</script>',
        'brand' => '<i>Marca</i>',
        'description' => '<script>alert(2)</script>',
    ]);

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertDontSee('<b>Remera</b>', false)
        ->assertDontSee('<i>Marca</i>', false);
});

it('nunca expone el costo ni el SKU interno', function () {
    $product = shopProduct(['name' => 'Remera lisa', 'sku' => 'SECRETO-123', 'cost' => '654.32', 'price' => '1000.00']);

    foreach (['/catalogo', '/producto/'.$product->slug] as $path) {
        $this->get($this->base.$path)
            ->assertOk()
            ->assertDontSee('SECRETO-123')
            ->assertDontSee('654,32')
            ->assertDontSee('654.32');
    }
});

it('la ficha sugiere otros productos de la misma categoría', function () {
    $category = Category::factory()->create(['name' => 'Remeras']);
    $main = shopProduct(['name' => 'Remera principal', 'category_id' => $category->id]);
    shopProduct(['name' => 'Remera hermana', 'category_id' => $category->id]);
    shopProduct(['name' => 'Remera borrador', 'category_id' => $category->id, 'is_published' => false]);
    shopProduct(['name' => 'Gorra de otra categoría']);

    $this->get($this->base.'/producto/'.$main->slug)
        ->assertSee('También te puede interesar')
        ->assertSee('Remera hermana')
        ->assertDontSee('Remera borrador')
        ->assertDontSee('Gorra de otra categoría');
});

// --- Landing --------------------------------------------------------------

it('la sección Catálogo de la landing muestra categorías con enlace y solo los destacados publicados', function () {
    $category = Category::factory()->create(['name' => 'Remeras']);
    $empty = Category::factory()->create(['name' => 'Sin productos']);
    shopProduct(['name' => 'Producto destacado', 'category_id' => $category->id, 'is_featured' => true]);
    shopProduct(['name' => 'Producto común', 'category_id' => $category->id]);
    shopProduct(['name' => 'Destacado borrador', 'category_id' => $category->id, 'is_featured' => true, 'is_published' => false]);

    $this->get($this->base.'/')
        ->assertSee('id="catalog"', false)
        ->assertSee('Remeras')
        ->assertSee('href="'.url('/catalogo/'.$category->slug).'"', false)
        ->assertSee('Producto destacado')
        ->assertDontSee('Producto común')
        ->assertDontSee('Destacado borrador')
        ->assertDontSee('Sin productos')
        ->assertSee('Ver catálogo completo');

    $this->get($this->base.'/catalogo')->assertSee('Producto común')->assertSee('Producto destacado');
});

it('la sección Catálogo se oculta sin contenido o si se desactiva, pero el menú sigue enlazando', function () {
    $this->get($this->base.'/')->assertDontSee('id="catalog"', false)->assertDontSee('href="'.url('/catalogo').'"', false);

    $category = Category::factory()->create(['name' => 'Remeras']);
    shopProduct(['category_id' => $category->id]);

    $this->get($this->base.'/')->assertSee('id="catalog"', false)->assertSee('href="'.url('/catalogo').'"', false);

    $sections = app(SiteContent::class)->all()['sections'];
    shopSite(['sections' => [...$sections, 'catalog' => false]]);

    $this->get($this->base.'/')
        ->assertDontSee('id="catalog"', false)
        ->assertSee('href="'.url('/catalogo').'"', false);
});

it('el menú de las páginas del catálogo lleva de vuelta a las secciones de la landing', function () {
    shopSite(['faq_items' => [['question' => '¿Envíos?', 'answer' => 'Sí']]]);
    $product = shopProduct();

    $this->get($this->base.'/producto/'.$product->slug)
        ->assertSee('href="'.url('/').'#faq"', false)
        ->assertSee('Ingresar al sistema');
});

// --- Temas ----------------------------------------------------------------

it('todas las páginas del catálogo responden con cada tema y combinación', function (string $theme, string $palette) {
    Modules::enable(Module::Variants);
    Modules::enable(Module::Stock);
    Storage::disk('public')->put('products/a.jpg', 'a');
    shopSite(['whatsapp' => '+54 9 11 1234-5678']);

    $appearance = app(Appearance::class);
    $appearance->setTheme($theme);
    $appearance->setPalette($palette);

    $category = Category::factory()->create(['name' => 'Remeras']);
    $product = shopProduct(['name' => 'Remera lisa', 'category_id' => $category->id, 'is_featured' => true, 'images' => ['products/a.jpg']]);
    $product->variants()->create(['name' => 'M', 'position' => 1]);
    shopProduct(['name' => 'Remera hermana', 'category_id' => $category->id]);

    foreach (['/', '/catalogo', '/catalogo/'.$category->slug, '/catalogo?q=remera', '/producto/'.$product->slug] as $path) {
        $this->get($this->base.$path)
            ->assertOk()
            ->assertSee("data-theme=\"{$theme}\"", false)
            ->assertSee('Remera lisa');
    }
})->with(function () {
    $themes = require __DIR__.'/../../../config/themes.php';

    foreach ($themes['themes'] as $themeKey => $theme) {
        foreach (array_keys($theme['palettes']) as $paletteKey) {
            yield "{$themeKey}/{$paletteKey}" => [$themeKey, $paletteKey];
        }
    }
});

it('cada tema tiene sus vistas del catálogo', function (string $theme) {
    foreach (['layout', 'catalog-index', 'catalog-show', 'product-card', 'catalog'] as $view) {
        expect(file_exists(resource_path("views/landings/{$theme}/{$view}.blade.php")))->toBeTrue("falta {$view} en {$theme}");
    }
})->with(['clasico', 'boutique']);
