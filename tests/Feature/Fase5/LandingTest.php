<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Services\Appearance;
use App\Services\SiteContent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    resetModules();
    $this->withoutVite();

    $this->url = 'http://'.config('domains.landing');
});

function landing(mixed $test, string $suffix = ''): TestResponse
{
    return $test->get($test->url.'/'.$suffix);
}

function saveSite(array $content): void
{
    $site = app(SiteContent::class);
    $site->save(array_merge($site->all(), $content));
}

// --- Contenido ------------------------------------------------------------

it('sin configurar muestra el nombre del negocio y el botón para ingresar', function () {
    landing($this)
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Ingresar al sistema')
        ->assertSee('data-theme="clasico"', false);
});

it('muestra el contenido editado', function () {
    saveSite([
        'name' => 'Casa Lila',
        'tagline' => 'Ropa con historia',
        'hero_title' => 'Vestite como te sentís',
        'hero_subtitle' => 'Colecciones nuevas todas las semanas.',
        'value_title' => 'Lo que nos distingue',
        'value_items' => [
            ['title' => 'Envíos rápidos', 'text' => 'Llegamos en 48 horas.'],
            ['title' => 'Cambios sin vueltas', 'text' => ''],
        ],
        'faq_items' => [['question' => '¿Hacen envíos?', 'answer' => 'Sí, a todo el país.']],
        'phone' => '011 4444-5555',
        'whatsapp' => '+54 9 11 1234-5678',
        'email' => 'hola@casalila.test',
        'address' => 'Calle Falsa 123',
        'instagram' => 'https://instagram.com/casalila',
    ]);

    landing($this)
        ->assertOk()
        ->assertSee('<title>Casa Lila — Ropa con historia</title>', false)
        ->assertSee('Vestite como te sentís')
        ->assertSee('Colecciones nuevas todas las semanas.')
        ->assertSee('Lo que nos distingue')
        ->assertSee('Envíos rápidos')
        ->assertSee('Llegamos en 48 horas.')
        ->assertSee('Cambios sin vueltas')
        ->assertSee('¿Hacen envíos?')
        ->assertSee('Sí, a todo el país.')
        ->assertSee('Calle Falsa 123')
        ->assertSee('tel:0114444555', false)
        ->assertSee('https://wa.me/5491112345678', false)
        ->assertSee('mailto:hola@casalila.test', false)
        ->assertSee('https://instagram.com/casalila', false);
});

it('el título de la portada es el nombre del negocio si no se indica otro', function () {
    saveSite(['name' => 'Casa Lila', 'hero_title' => '']);

    landing($this)->assertSee('<h1', false)->assertSee('Casa Lila');
});

it('escapa el contenido para que no se pueda inyectar código', function () {
    saveSite(['hero_title' => '<script>alert(1)</script>', 'name' => 'Casa <b>Lila</b>']);

    landing($this)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('Casa <b>Lila</b>', false);
});

// --- Secciones ------------------------------------------------------------

it('cada sección se puede ocultar y volver a mostrar', function (string $section, array $content, string $marker) {
    saveSite($content);

    landing($this)->assertSee("id=\"{$section}\"", false)->assertSee($marker);

    $sections = app(SiteContent::class)->all()['sections'];
    saveSite(['sections' => [...$sections, $section => false]]);

    landing($this)->assertDontSee("id=\"{$section}\"", false)->assertDontSee($marker);

    saveSite(['sections' => [...$sections, $section => true]]);

    landing($this)->assertSee("id=\"{$section}\"", false);
})->with([
    'propuesta' => ['value', ['value_items' => [['title' => 'Marca propia', 'text' => '']]], 'Marca propia'],
    'preguntas' => ['faq', ['faq_items' => [['question' => '¿Aceptan tarjeta?', 'answer' => 'Sí.']]], '¿Aceptan tarjeta?'],
    'contacto' => ['contact', ['address' => 'Av. Siempre Viva 742'], 'Av. Siempre Viva 742'],
]);

it('la portada también se puede ocultar', function () {
    saveSite(['sections' => ['hero' => false]]);

    landing($this)->assertDontSee('id="hero"', false);
});

it('las secciones sin contenido no se muestran aunque estén activas', function () {
    landing($this)
        ->assertSee('id="hero"', false)
        ->assertDontSee('id="value"', false)
        ->assertDontSee('id="faq"', false)
        ->assertDontSee('id="contact"', false)
        ->assertDontSee('id="catalog"', false);
});

it('la sección Catálogo solo aparece con el módulo encendido y categorías activas', function () {
    $remeras = Category::factory()->create(['name' => 'Remeras', 'active' => true]);
    Product::factory()->create(['category_id' => $remeras->id, 'is_published' => true]);
    $oculta = Category::factory()->create(['name' => 'Oculta', 'active' => false]);
    Product::factory()->create(['category_id' => $oculta->id, 'is_published' => true]);

    landing($this)->assertDontSee('id="catalog"', false)->assertDontSee('Remeras');

    Modules::enable(Module::Catalog);

    landing($this)->assertSee('id="catalog"', false)->assertSee('Remeras')->assertDontSee('Oculta');

    $sections = app(SiteContent::class)->all()['sections'];
    saveSite(['sections' => [...$sections, 'catalog' => false]]);

    landing($this)->assertDontSee('id="catalog"', false);
});

// --- Enlace al panel ------------------------------------------------------

it('el botón Ingresar al sistema conserva esquema y puerto', function () {
    $this->get($this->url.':8000/')
        ->assertSee('href="http://'.config('domains.panel').':8000"', false);

    $this->get($this->url.'/')
        ->assertSee('href="http://'.config('domains.panel').'"', false);

    $this->get($this->url.'/', ['X-Forwarded-Proto' => 'https'])
        ->assertSee('href="https://'.config('domains.panel').'"', false);
});

// --- Temas y combinaciones ------------------------------------------------

it('cambiar de tema cambia el diseño', function () {
    $html = landing($this)->assertOk()->assertSee('data-theme="clasico"', false)->getContent();

    app(Appearance::class)->setTheme('boutique');

    landing($this)
        ->assertOk()
        ->assertSee('data-theme="boutique"', false)
        ->assertDontSee('data-theme="clasico"', false);

    expect(landing($this)->getContent())->not->toBe($html);
});

it('cambiar la combinación cambia las variables de color', function () {
    landing($this)->assertSee('--brand:#b45309', false);

    app(Appearance::class)->setPalette('oceano');

    landing($this)->assertSee('--brand:#0369a1', false)->assertDontSee('--brand:#b45309', false);

    app(Appearance::class)->setTheme('boutique');
    app(Appearance::class)->setPalette('tinta');

    landing($this)->assertSee('--brand:#1e3a8a', false)->assertSee('--font-heading:Georgia', false);
});

it('todos los temas y combinaciones responden con y sin contenido cargado', function (string $theme, string $palette) {
    $appearance = app(Appearance::class);
    $appearance->setTheme($theme);
    $appearance->setPalette($palette);

    landing($this)->assertOk()->assertSee('Ingresar al sistema');

    Modules::enable(Module::Catalog);
    $accesorios = Category::factory()->create(['name' => 'Accesorios']);
    Product::factory()->create(['category_id' => $accesorios->id, 'is_published' => true]);
    saveSite([
        'name' => 'Casa Lila',
        'value_items' => [['title' => 'Uno', 'text' => 'Texto']],
        'faq_items' => [['question' => 'Pregunta', 'answer' => 'Respuesta']],
        'phone' => '123',
        'whatsapp' => '+54 9 11 1',
        'email' => 'a@b.test',
        'instagram' => 'https://instagram.com/x',
    ]);

    landing($this)
        ->assertOk()
        ->assertSee('id="value"', false)
        ->assertSee('id="catalog"', false)
        ->assertSee('id="faq"', false)
        ->assertSee('id="contact"', false);
})->with(function () {
    $themes = require __DIR__.'/../../../config/themes.php';

    foreach ($themes['themes'] as $themeKey => $theme) {
        foreach (array_keys($theme['palettes']) as $paletteKey) {
            yield "{$themeKey}/{$paletteKey}" => [$themeKey, $paletteKey];
        }
    }
});

// --- Marca ----------------------------------------------------------------

it('sin logo muestra el nombre del negocio como texto', function (string $theme) {
    app(Appearance::class)->setTheme($theme);
    saveSite(['name' => 'Casa Lila']);

    landing($this)
        ->assertSee('Casa Lila')
        ->assertDontSee('storage/branding', false)
        ->assertDontSee('<img', false);
})->with(['clasico', 'boutique']);

it('con logo lo usa en lugar del nombre', function (string $theme) {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'png');
    Storage::disk('public')->put('branding/logo-oscuro.png', 'png');

    $appearance = app(Appearance::class);
    $appearance->setTheme($theme);
    $appearance->setFile('logo_light', 'branding/logo.png');
    $appearance->setFile('logo_dark', 'branding/logo-oscuro.png');
    saveSite(['name' => 'Casa Lila']);

    $html = landing($this)->getContent();

    // El tema Boutique tiene el encabezado oscuro: usa el logo para fondo oscuro.
    expect($html)->toContain($theme === 'boutique' ? 'storage/branding/logo-oscuro.png' : 'storage/branding/logo.png');
})->with(['clasico', 'boutique']);

it('el tema Clásico muestra el logo para fondo oscuro en modo oscuro', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'png');
    Storage::disk('public')->put('branding/logo-oscuro.png', 'png');
    $appearance = app(Appearance::class);
    $appearance->setFile('logo_light', 'branding/logo.png');
    $appearance->setFile('logo_dark', 'branding/logo-oscuro.png');

    landing($this)
        ->assertSee('storage/branding/logo.png', false)
        ->assertSee('storage/branding/logo-oscuro.png', false)
        ->assertSee('dark:block', false);
});

it('usa el favicon y la imagen para compartir cargados', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/fav.png', 'png');
    Storage::disk('public')->put('branding/share.jpg', 'jpg');
    $appearance = app(Appearance::class);

    landing($this)->assertDontSee('rel="icon"', false)->assertDontSee('og:image', false);

    $appearance->setFile('favicon', 'branding/fav.png');
    $appearance->setFile('share_image', 'branding/share.jpg');
    saveSite(['name' => 'Casa Lila', 'tagline' => 'Ropa con historia']);

    landing($this)
        ->assertSee('<link rel="icon" href="', false)
        ->assertSee('storage/branding/fav.png', false)
        ->assertSee('<meta property="og:image" content="', false)
        ->assertSee('storage/branding/share.jpg', false)
        ->assertSee('<meta property="og:title" content="Casa Lila">', false)
        ->assertSee('<meta name="description" content="Ropa con historia">', false);
});

it('usa las imágenes propias del tema elegido', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/hero.jpg', 'jpg');
    $appearance = app(Appearance::class);
    $appearance->setFile($appearance->assetSlot('hero'), 'branding/hero.jpg');

    landing($this)->assertSee('storage/branding/hero.jpg', false);

    // La imagen es del tema Clásico: Boutique no la usa.
    $appearance->setTheme('boutique');

    landing($this)->assertDontSee('storage/branding/hero.jpg', false);
});

it('un host desconocido sigue respondiendo 404', function () {
    $this->get('http://desconocido.localhost/')->assertNotFound();
});
