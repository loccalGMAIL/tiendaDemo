<?php

use App\Filament\Superadmin\Pages\ManageAppearance;
use App\Models\User;
use App\Services\Appearance;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    $this->url = 'http://'.config('domains.superadmin').'/appearance';
    $this->superadmin = User::factory()->superadmin()->create();
});

function appearancePage(mixed $user = null): Testable
{
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    return Livewire::actingAs($user ?? User::factory()->superadmin()->create())->test(ManageAppearance::class);
}

// --- Acceso ---------------------------------------------------------------

it('solo el SuperAdmin entra a Apariencia', function () {
    $this->actingAs(User::factory()->admin()->create())->get($this->url)->assertForbidden();
    $this->actingAs(User::factory()->seller()->create())->get($this->url)->assertForbidden();
    $this->actingAs($this->superadmin)->get($this->url)->assertOk()->assertSee('Apariencia');
});

it('el cliente no ve ni encuentra Apariencia en su panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('http://'.config('domains.panel').'/appearance')->assertNotFound();

    $this->actingAs($admin)
        ->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertDontSee('Apariencia')
        ->assertDontSee(config('domains.superadmin'));
});

// --- Tema y combinación ---------------------------------------------------

it('muestra la apariencia actual', function () {
    app(Appearance::class)->setTheme('boutique');
    app(Appearance::class)->setPalette('salvia');

    appearancePage()
        ->assertSet('data.theme', 'boutique')
        ->assertSet('data.palette', 'salvia')
        ->assertSee('Clásico')
        ->assertSee('Boutique');
});

it('al cambiar de tema la combinación vuelve a la primera del tema nuevo', function () {
    appearancePage()
        ->set('data.palette', 'rosa')
        ->set('data.theme', 'boutique')
        ->assertSet('data.palette', 'noche');
});

it('guarda el tema y la combinación', function () {
    appearancePage()
        ->set('data.theme', 'boutique')
        ->set('data.palette', 'tinta')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Apariencia actualizada');

    $appearance = app(Appearance::class);

    expect($appearance->themeKey())->toBe('boutique')->and($appearance->paletteKey())->toBe('tinta');
});

it('guarda una combinación del mismo tema sin cambiar de tema', function () {
    appearancePage()
        ->set('data.palette', 'oceano')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Appearance::class)->themeKey())->toBe('clasico')
        ->and(app(Appearance::class)->paletteKey())->toBe('oceano');
});

it('rechaza una combinación que no es del tema elegido', function () {
    appearancePage()
        ->set('data.theme', 'boutique')
        ->set('data.palette', 'oceano')
        ->call('save')
        ->assertHasFormErrors(['palette']);

    expect(app(Appearance::class)->themeKey())->toBe('clasico');
});

// --- Archivos -------------------------------------------------------------

it('sube los logos, el favicon y la imagen para compartir al disco público', function () {
    appearancePage()
        ->fillForm([
            'logo_light' => UploadedFile::fake()->image('claro.png', 400, 120),
            'logo_dark' => UploadedFile::fake()->image('oscuro.png', 400, 120),
            'logo_login' => UploadedFile::fake()->image('acceso.png', 400, 120),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
            'share_image' => UploadedFile::fake()->image('compartir.jpg', 1200, 630),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $appearance = app(Appearance::class);

    foreach (['logo_light', 'logo_dark', 'logo_login', 'favicon', 'share_image'] as $slot) {
        $path = $appearance->files()[$slot];

        expect($path)->toStartWith('branding/');
        Storage::disk('public')->assertExists($path);
    }

    expect($appearance->logoUrl('light'))->not->toBeNull()
        ->and($appearance->faviconUrl())->not->toBeNull()
        ->and($appearance->shareImageUrl())->not->toBeNull();
});

it('quitar un logo borra el archivo y vuelve a mostrar el nombre', function () {
    $page = appearancePage()
        ->fillForm(['logo_light' => UploadedFile::fake()->image('claro.png')])
        ->call('save');

    $path = app(Appearance::class)->files()['logo_light'];
    Storage::disk('public')->assertExists($path);

    $page->fillForm(['logo_light' => null])->call('save')->assertHasNoFormErrors();

    Storage::disk('public')->assertMissing($path);
    expect(app(Appearance::class)->logoUrl('light'))->toBeNull()
        ->and(app(Appearance::class)->files())->not->toHaveKey('logo_light');
});

it('no acepta archivos que no son imágenes', function () {
    appearancePage()
        ->fillForm(['logo_light' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['logo_light']);

    expect(app(Appearance::class)->files())->toBe([]);
});

it('el favicon acepta png y rechaza archivos que no son imágenes', function () {
    appearancePage()
        ->fillForm(['favicon' => UploadedFile::fake()->create('favicon.pdf', 10, 'application/pdf')])
        ->call('save')
        ->assertHasFormErrors(['favicon']);

    appearancePage()
        ->fillForm(['favicon' => UploadedFile::fake()->image('favicon.png', 32, 32)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Appearance::class)->files())->toHaveKey('favicon');
});

it('no acepta imágenes demasiado pesadas', function () {
    appearancePage()
        ->fillForm(['logo_light' => UploadedFile::fake()->image('enorme.png')->size(5000)])
        ->call('save')
        ->assertHasFormErrors(['logo_light']);
});

it('sube los recursos gráficos del tema elegido', function () {
    appearancePage()
        ->fillForm(['assets' => ['clasico' => ['hero' => UploadedFile::fake()->image('hero.jpg', 1200, 800)]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $appearance = app(Appearance::class);

    expect($appearance->files())->toHaveKey('asset:clasico:hero')
        ->and($appearance->assetUrl('hero'))->not->toBeNull();
});

it('los recursos de un tema no se pierden al guardar con otro tema', function () {
    appearancePage()
        ->fillForm(['assets' => ['clasico' => ['hero' => UploadedFile::fake()->image('hero.jpg')]]])
        ->call('save');

    $path = app(Appearance::class)->files()['asset:clasico:hero'];

    appearancePage()
        ->set('data.theme', 'boutique')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Appearance::class)->themeKey())->toBe('boutique')
        ->and(app(Appearance::class)->files()['asset:clasico:hero'])->toBe($path);
    Storage::disk('public')->assertExists($path);

    // Al volver al tema Clásico, su imagen sigue ahí.
    app(Appearance::class)->setTheme('clasico');

    expect(app(Appearance::class)->assetUrl('hero'))->not->toBeNull();
});

it('el tema Boutique tiene sus propios recursos', function () {
    appearancePage()->set('data.theme', 'boutique')->call('save')->assertHasNoFormErrors();

    // Con el tema ya elegido, la pantalla ofrece los recursos de Boutique.
    appearancePage()
        ->fillForm(['assets' => ['boutique' => ['about' => UploadedFile::fake()->image('about.jpg')]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Appearance::class)->files())->toHaveKey('asset:boutique:about')
        ->and(app(Appearance::class)->assetUrl('about'))->not->toBeNull();
});
