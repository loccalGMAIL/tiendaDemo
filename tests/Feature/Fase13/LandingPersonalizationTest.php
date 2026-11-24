<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\SiteSettings;
use App\Filament\Superadmin\Pages\ManageAppearance;
use App\Models\Product;
use App\Models\User;
use App\Services\Appearance;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    $this->withoutVite();
    $this->url = 'http://'.config('domains.landing').'/';
});

it('muestra los bloques comerciales sin catálogo en ambos temas', function (string $theme) {
    app(Appearance::class)->setTheme($theme);
    $site = app(SiteContent::class);
    $site->save([
        'about_title' => 'Un oficio de familia',
        'about_text' => 'Trabajamos desde 1980.',
        'services_title' => 'Nuestros servicios',
        'service_items' => [['title' => 'Asesoramiento', 'text' => 'Atención personalizada.']],
        'banner_title' => 'Nueva sucursal',
        'banner_text' => 'Vení a conocernos.',
        'hours' => 'Lunes a viernes de 9 a 18',
    ]);
    $site->saveStructure(['hero', 'services', 'about', 'catalog', 'banner', 'contact']);

    $this->get($this->url)
        ->assertOk()
        ->assertSeeInOrder(['id="hero"', 'id="services"', 'id="about"', 'id="banner"', 'id="contact"'], false)
        ->assertSee('Asesoramiento')
        ->assertSee('Trabajamos desde 1980.')
        ->assertSee('Nueva sucursal')
        ->assertSee('Lunes a viernes de 9 a 18')
        ->assertDontSee('id="catalog"', false)
        ->assertDontSee('Ver catálogo')
        ->assertSee('<summary', false);
})->with(['clasico', 'boutique']);

it('el menú del celular es el ícono hamburguesa, sin la palabra «Menú»', function (string $theme) {
    app(Appearance::class)->setTheme($theme);
    $site = app(SiteContent::class);
    $site->save(['about_text' => 'Trabajamos desde 1980.']);
    $site->saveStructure(['hero', 'about']);

    $this->get($this->url)
        ->assertOk()
        ->assertSee('aria-label="Menú de secciones"', false)
        ->assertSee('M4 6h16M4 12h16M4 18h16', false)
        ->assertDontSee('>Menú</summary>', false);
})->with(['clasico', 'boutique']);

it('solo muestra secciones configuradas y conserva el contenido al ocultarlas', function () {
    $site = app(SiteContent::class);
    $site->save(['about_text' => 'Nuestra historia']);
    $site->saveStructure(['hero']);

    $this->get($this->url)->assertOk()->assertDontSee('Nuestra historia');

    $site->saveStructure(['about', 'hero']);

    $this->get($this->url)->assertOk()->assertSeeInOrder(['id="about"', 'id="hero"'], false);
    expect($site->all()['about_text'])->toBe('Nuestra historia');
});

it('no renderiza bloques vacíos ni nombres de sección desconocidos', function () {
    app(SiteContent::class)->saveStructure(['hero', '../../secret', 'about', 'services', 'gallery', 'banner', 'hero']);

    $this->get($this->url)->assertOk()
        ->assertDontSee('id="about"', false)
        ->assertDontSee('id="services"', false)
        ->assertDontSee('id="gallery"', false)
        ->assertDontSee('id="banner"', false);

    expect(app(SiteContent::class)->sectionOrder())->toBe(['hero', 'about', 'services', 'gallery', 'banner']);
});

it('prioriza el catálogo solo cuando hay productos públicos', function (string $theme) {
    app(Appearance::class)->setTheme($theme);
    app(SiteContent::class)->save(['whatsapp' => '+54 9 11 12345678']);
    Modules::enable(Module::Catalog);

    $this->get($this->url)->assertOk()->assertDontSee('Ver catálogo')->assertSee('Escribinos por WhatsApp');

    Product::factory()->create(['is_published' => true]);

    $this->get($this->url)->assertOk()->assertSee('Ver catálogo')->assertSee('Consultar por WhatsApp');

    Modules::disable(Module::Catalog);

    $this->get($this->url)->assertOk()->assertDontSee('Ver catálogo')->assertSee('Escribinos por WhatsApp');
})->with(['clasico', 'boutique']);

it('mantiene Ingresar en el encabezado y el pie con o sin catálogo', function (string $theme, bool $withCatalog) {
    app(Appearance::class)->setTheme($theme);

    if ($withCatalog) {
        Modules::enable(Module::Catalog);
        Product::factory()->create(['is_published' => true]);
    }

    $response = $this->get($this->url)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $headerLinks = $xpath->query('//header//a[normalize-space()="Ingresar"]');
    $footerLinks = $xpath->query('//footer//a[normalize-space()="Ingresar al sistema"]');

    expect($headerLinks)->toHaveCount(1);
    expect($headerLinks->item(0)->getAttribute('href'))->toBe('http://'.config('domains.panel'));
    expect($footerLinks)->toHaveCount(1);
    expect($footerLinks->item(0)->getAttribute('href'))->toBe('http://'.config('domains.panel'));
    expect($xpath->query('//header//a[normalize-space()="Catálogo"]'))->toHaveCount($withCatalog ? 1 : 0);
})->with(['clasico', 'boutique'])->with([false, true]);

it('conserva esquema y puerto en el botón de ingreso', function (string $theme) {
    app(Appearance::class)->setTheme($theme);

    $this->get('https://'.config('domains.landing').':8443/')
        ->assertOk()
        ->assertSee('href="https://'.config('domains.panel').':8443"', false)
        ->assertSee('>Ingresar</a>', false);
})->with(['clasico', 'boutique']);

it('usa fotografías de contenido y omite archivos inexistentes o rutas ajenas', function (string $theme) {
    Storage::fake('public');
    Storage::disk('public')->put('site/portada.jpg', 'image');
    Storage::disk('public')->put('site/local.jpg', 'image');
    $site = app(SiteContent::class);
    $site->save([
        'hero_image' => 'site/portada.jpg',
        'hero_image_alt' => 'Nuestro local',
        'gallery_items' => [
            ['image' => 'site/local.jpg', 'alt' => 'El salón'],
            ['image' => 'site/faltante.jpg', 'alt' => 'No disponible'],
            ['image' => 'branding/privada.jpg', 'alt' => 'Ajena'],
        ],
    ]);
    app(Appearance::class)->setTheme($theme);

    $this->get($this->url)->assertOk()
        ->assertSee('storage/site/portada.jpg', false)
        ->assertSee('storage/site/local.jpg', false)
        ->assertSee('El salón')
        ->assertDontSee('No disponible')
        ->assertDontSee('Ajena');
    expect($site->imageUrl('site/../branding/privada.jpg'))->toBeNull();
})->with(['clasico', 'boutique']);

it('escapa el contenido de los nuevos bloques', function (string $theme) {
    app(Appearance::class)->setTheme($theme);
    $unsafe = '<script>alert(1)</script>';
    app(SiteContent::class)->save([
        'about_text' => $unsafe,
        'service_items' => [['title' => $unsafe, 'text' => $unsafe]],
        'banner_title' => $unsafe,
        'banner_text' => $unsafe,
        'hours' => $unsafe,
    ]);

    $this->get($this->url)->assertOk()
        ->assertDontSee($unsafe, false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
})->with(['clasico', 'boutique']);

it('el SuperAdmin guarda la estructura desde Apariencia', function () {
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(ManageAppearance::class)
        ->fillForm(['landing_sections' => ['services', 'hero']])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Apariencia actualizada');

    expect(app(SiteContent::class)->sectionOrder())->toBe(['services', 'hero']);
});

it('el cliente guarda contenido pero no puede alterar la estructura ni los campos ocultos', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());
    $site = app(SiteContent::class);
    $site->save(['about_text' => 'Contenido protegido']);
    $site->saveStructure(['hero', 'services']);

    Livewire::test(SiteSettings::class)
        ->fillForm(['service_items' => [['title' => 'Reparaciones', 'text' => 'En el día']]])
        ->set('data.sections', ['services' => false])
        ->set('data.about_text', 'Alterado')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($site->sectionOrder())->toBe(['hero', 'services']);
    expect($site->all()['about_text'])->toBe('Contenido protegido');
    expect($site->all()['service_items'][0]['title'])->toBe('Reparaciones');
});

it('no permite que el cliente cargue SVG como fotografía', function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(SiteSettings::class)
        ->fillForm(['hero_image' => UploadedFile::fake()->createWithContent('imagen.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->call('save')
        ->assertHasFormErrors(['hero_image']);

    expect(app(SiteContent::class)->all()['hero_image'])->toBeNull();
});

it('guarda una fotografía del cliente y permite retirarla sin borrar el archivo', function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'hero_image' => UploadedFile::fake()->image('local.jpg', 1200, 800),
            'hero_image_alt' => 'Frente del negocio',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(SiteContent::class)->all()['hero_image'];
    Storage::disk('public')->assertExists($path);
    $this->get($this->url)->assertOk()->assertSee('Frente del negocio')->assertSee('storage/'.$path, false);

    Livewire::test(SiteSettings::class)
        ->fillForm(['hero_image' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SiteContent::class)->all()['hero_image'])->toBeNull();
    Storage::disk('public')->assertExists($path);
    $this->get($this->url)->assertOk()->assertDontSee('storage/'.$path, false);
});

it('rechaza fotografías de más de cuatro megabytes', function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(SiteSettings::class)
        ->fillForm(['hero_image' => UploadedFile::fake()->image('enorme.jpg')->size(4097)])
        ->call('save')
        ->assertHasFormErrors(['hero_image']);

    expect(app(SiteContent::class)->all()['hero_image'])->toBeNull();
});

it('una estructura vacía no vuelve a habilitar las secciones por defecto', function () {
    app(SiteContent::class)->saveStructure([]);

    $this->get($this->url)->assertOk()->assertDontSee('id="hero"', false);
    expect(app(SiteContent::class)->sectionOrder())->toBe([]);
});

it('la portada sin catálogo ni WhatsApp enlaza solo a secciones disponibles', function () {
    $site = app(SiteContent::class);
    $site->save(['service_items' => [['title' => 'Reparación', 'text' => 'En el local']]]);
    $site->saveStructure(['hero', 'services', 'contact']);

    $this->get($this->url)->assertOk()->assertSee('Conocé nuestros servicios')->assertDontSee('Contactanos');

    $site->save(array_merge($site->all(), ['address' => 'Mitre 123']));

    $this->get($this->url)->assertOk()->assertSee('Contactanos');
});

it('el acceso del personal queda en el pie y no en el encabezado', function (string $theme) {
    app(Appearance::class)->setTheme($theme);

    $this->get($this->url)->assertOk()
        ->assertSeeInOrder(['</header>', '<footer', 'Ingresar al sistema'], false);
})->with(['clasico', 'boutique']);
