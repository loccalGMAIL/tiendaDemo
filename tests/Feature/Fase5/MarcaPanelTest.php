<?php

use App\Models\User;
use App\Services\Appearance;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    $this->panel = 'http://'.config('domains.panel');
    $this->superadmin = 'http://'.config('domains.superadmin');
});

function brandSite(array $content): void
{
    $site = app(SiteContent::class);
    $site->save(array_merge($site->all(), $content));
}

it('el panel muestra el nombre del negocio cuando no hay logo', function () {
    brandSite(['name' => 'Casa Lila']);

    $this->get($this->panel.'/login')->assertOk()->assertSee('Casa Lila')->assertDontSee('<img', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get($this->panel.'/')
        ->assertOk()
        ->assertSee('Casa Lila');
});

it('el panel usa el logo para fondo claro y el de fondo oscuro', function () {
    Storage::disk('public')->put('branding/logo.png', 'png');
    Storage::disk('public')->put('branding/oscuro.png', 'png');
    app(Appearance::class)->setFile('logo_light', 'branding/logo.png');
    app(Appearance::class)->setFile('logo_dark', 'branding/oscuro.png');

    $this->actingAs(User::factory()->admin()->create())
        ->get($this->panel.'/')
        ->assertOk()
        ->assertSee('storage/branding/logo.png', false)
        ->assertSee('storage/branding/oscuro.png', false);
});

it('el acceso usa su propio logo y el resto del panel usa el claro', function () {
    Storage::disk('public')->put('branding/logo.png', 'png');
    Storage::disk('public')->put('branding/acceso.png', 'png');
    app(Appearance::class)->setFile('logo_light', 'branding/logo.png');
    app(Appearance::class)->setFile('logo_login', 'branding/acceso.png');

    $this->get($this->panel.'/login')
        ->assertOk()
        ->assertSee('storage/branding/acceso.png', false)
        ->assertDontSee('storage/branding/logo.png', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get($this->panel.'/')
        ->assertSee('storage/branding/logo.png', false)
        ->assertDontSee('storage/branding/acceso.png', false);
});

it('sin logo propio el acceso usa el logo claro', function () {
    Storage::disk('public')->put('branding/logo.png', 'png');
    app(Appearance::class)->setFile('logo_light', 'branding/logo.png');

    $this->get($this->panel.'/login')->assertSee('storage/branding/logo.png', false);
});

it('el favicon cargado se usa en el panel', function () {
    Storage::disk('public')->put('branding/fav.png', 'png');

    $this->get($this->panel.'/login')->assertDontSee('storage/branding/fav.png', false);

    app(Appearance::class)->setFile('favicon', 'branding/fav.png');

    $this->get($this->panel.'/login')->assertSee('storage/branding/fav.png', false);
});

it('el color principal de los paneles sale de la combinación elegida', function () {
    $appearance = app(Appearance::class);

    expect(Filament::getPanel('admin')->getColors()['primary'])->toBe(Color::hex('#b45309'))
        ->and(Filament::getPanel('superadmin')->getColors()['primary'])->toBe(Color::hex('#b45309'));

    $appearance->setPalette('bosque');

    expect(Filament::getPanel('admin')->getColors()['primary'])->toBe(Color::hex('#15803d'))
        ->and(Filament::getPanel('superadmin')->getColors()['primary'])->toBe(Color::hex('#15803d'));

    $appearance->setTheme('boutique');
    $appearance->setPalette('vino');

    expect(Filament::getPanel('admin')->getColors()['primary'])->toBe(Color::hex('#7f1d1d'));
});

it('la tipografía de los títulos del panel sale del tema', function () {
    $this->get($this->panel.'/login')->assertSee('--theme-heading-font:', false)->assertDontSee('Georgia');

    app(Appearance::class)->setTheme('boutique');

    $this->get($this->panel.'/login')->assertSee('--theme-heading-font: Georgia', false);
});

it('el panel superadmin usa la misma marca', function () {
    brandSite(['name' => 'Casa Lila']);

    $this->get($this->superadmin.'/login')->assertOk()->assertSee('Casa Lila');
});

it('cambiar el nombre del sitio cambia el nombre del panel', function () {
    brandSite(['name' => 'Primero']);
    $this->get($this->panel.'/login')->assertSee('Primero');

    brandSite(['name' => 'Segundo']);
    $this->get($this->panel.'/login')->assertSee('Segundo')->assertDontSee('Primero');
});
