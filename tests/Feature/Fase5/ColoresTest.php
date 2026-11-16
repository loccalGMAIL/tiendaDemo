<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\ColorSettings;
use App\Models\User;
use App\Services\Appearance;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->url = 'http://'.config('domains.panel').'/color-settings';
});

it('Colores no existe con el módulo apagado', function () {
    $this->actingAs($this->admin)->get($this->url)->assertNotFound();

    expect(ColorSettings::canAccess())->toBeFalse();
});

it('Colores aparece en la navegación solo con el módulo encendido', function () {
    $this->actingAs($this->admin);

    expect(ColorSettings::shouldRegisterNavigation())->toBeFalse();

    Modules::enable(Module::CustomColors);

    expect(ColorSettings::shouldRegisterNavigation())->toBeTrue();
});

it('con el módulo encendido entra el administrador y no el vendedor', function () {
    Modules::enable(Module::CustomColors);

    $this->actingAs($this->admin)->get($this->url)->assertOk()->assertSee('Colores');
    $this->actingAs($this->seller)->get($this->url)->assertForbidden();
});

it('ofrece exactamente las combinaciones del tema actual', function () {
    Modules::enable(Module::CustomColors);
    $this->actingAs($this->admin);

    $page = Livewire::test(ColorSettings::class)->assertSet('palette', 'ambar');

    expect(array_keys($page->instance()->getPalettes()))->toBe(['ambar', 'oceano', 'bosque', 'rosa'])
        ->and($page->instance()->getThemeName())->toBe('Clásico');

    app(Appearance::class)->setTheme('boutique');

    $boutique = Livewire::test(ColorSettings::class)->assertSet('palette', 'noche');

    expect(array_keys($boutique->instance()->getPalettes()))->toBe(['noche', 'vino', 'salvia', 'tinta']);
});

it('muestra una vista previa con los colores de la combinación elegida', function () {
    Modules::enable(Module::CustomColors);
    $this->actingAs($this->admin);

    Livewire::test(ColorSettings::class)
        ->assertSee('Vista previa')
        ->assertSee('background-color: #b45309', false)
        ->assertSee('data-preview="ambar"', false)
        ->set('palette', 'oceano')
        ->assertSee('data-preview="oceano"', false)
        ->assertDontSee('data-preview="ambar"', false);
});

it('guarda la combinación y se refleja en la landing y en el panel', function () {
    Modules::enable(Module::CustomColors);
    $this->actingAs($this->admin);

    Livewire::test(ColorSettings::class)
        ->set('palette', 'bosque')
        ->call('save')
        ->assertNotified('Colores actualizados');

    expect(app(Appearance::class)->paletteKey())->toBe('bosque')
        ->and(Filament::getPanel('admin')->getColors()['primary'])->toBe(Color::hex('#15803d'));

    $this->withoutVite()
        ->get('http://'.config('domains.landing').'/')
        ->assertSee('--brand:#15803d', false);
});

it('no se puede elegir una combinación de otro tema', function () {
    Modules::enable(Module::CustomColors);
    $this->actingAs($this->admin);

    Livewire::test(ColorSettings::class)
        ->set('palette', 'noche')
        ->call('save')
        ->assertNotified();

    expect(app(Appearance::class)->paletteKey())->toBe('ambar');
});

it('el cliente no cambia el tema ni la marca desde Colores', function () {
    Modules::enable(Module::CustomColors);
    $this->actingAs($this->admin);

    // La pantalla solo tiene la combinación: ni tema, ni archivos, ni selector libre de color.
    expect(property_exists(ColorSettings::class, 'theme'))->toBeFalse()
        ->and(property_exists(ColorSettings::class, 'data'))->toBeFalse();

    $html = Livewire::test(ColorSettings::class)->html();

    expect($html)->not->toContain('type="color"')
        ->not->toContain('type="file"')
        ->not->toContain('Favicon')
        ->not->toContain('Logo');

    expect(fn () => Livewire::test(ColorSettings::class)->set('theme', 'boutique'))->toThrow(Exception::class);

    expect(app(Appearance::class)->themeKey())->toBe('clasico');
});

it('apagar el módulo no borra la combinación elegida', function () {
    Modules::enable(Module::CustomColors);
    app(Appearance::class)->setPalette('rosa');

    Modules::disable(Module::CustomColors);

    expect(app(Appearance::class)->paletteKey())->toBe('rosa');
});
