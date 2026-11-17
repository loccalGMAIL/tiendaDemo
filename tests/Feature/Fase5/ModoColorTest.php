<?php

use App\Filament\Superadmin\Pages\ManageAppearance;
use App\Models\User;
use App\Services\Appearance;
use App\Services\Settings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->withoutVite();

    $this->url = 'http://'.config('domains.landing');
});

function appearanceWithLogos(string $mode): Appearance
{
    Storage::disk('public')->put('branding/claro.png', 'png');
    Storage::disk('public')->put('branding/oscuro.png', 'png');

    $appearance = app(Appearance::class);
    $appearance->setFile('logo_light', 'branding/claro.png');
    $appearance->setFile('logo_dark', 'branding/oscuro.png');
    $appearance->setMode($mode);

    return $appearance;
}

// --- Modo -------------------------------------------------------------------

it('sin elegir, el modo es el automático de siempre', function () {
    expect(app(Appearance::class)->modeKey())->toBe('auto');
});

it('guarda el modo y rechaza uno que no existe', function () {
    $appearance = app(Appearance::class);

    $appearance->setMode('light');
    expect($appearance->modeKey())->toBe('light');

    expect(fn () => $appearance->setMode('sepia'))->toThrow(InvalidArgumentException::class)
        ->and($appearance->modeKey())->toBe('light');
});

it('un modo guardado que ya no existe vuelve al automático', function () {
    app(Settings::class)->set('appearance.mode', 'sepia');

    expect(app(Appearance::class)->modeKey())->toBe('auto');
});

// --- Variables CSS ------------------------------------------------------------

it('en modo claro la landing no tiene regla de modo oscuro', function () {
    $appearance = app(Appearance::class);
    $appearance->setMode('light');

    expect($appearance->cssVariables())
        ->toContain('--surface:#ffffff')
        ->toContain('color-scheme:light')
        ->not->toContain('prefers-color-scheme');
});

it('en modo oscuro usa siempre el fondo y el texto oscuros', function () {
    $appearance = app(Appearance::class);
    $appearance->setMode('dark');

    expect($appearance->cssVariables())
        ->toContain('--surface:#1c1917')
        ->toContain('--text:#fafaf9')
        ->toContain('color-scheme:dark')
        ->not->toContain('prefers-color-scheme');
});

it('en modo automático sigue al dispositivo', function () {
    $appearance = app(Appearance::class);
    $appearance->setMode('auto');

    expect($appearance->cssVariables())
        ->toContain('--surface:#ffffff')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('--surface:#1c1917');
});

it('el modo llega al HTML de la landing', function (string $mode, string $expected) {
    app(Appearance::class)->setMode($mode);

    $this->get($this->url.'/')->assertOk()->assertSee($expected, false);
})->with([
    'claro' => ['light', 'color-scheme:light'],
    'oscuro' => ['dark', 'color-scheme:dark'],
    'automático' => ['auto', '@media (prefers-color-scheme: dark)'],
]);

// --- Logos --------------------------------------------------------------------

it('en modo automático el Clásico lleva los dos logos y deja elegir al dispositivo', function () {
    appearanceWithLogos('auto');

    $this->get($this->url.'/')
        ->assertSee('storage/branding/claro.png', false)
        ->assertSee('storage/branding/oscuro.png', false)
        ->assertSee('dark:hidden', false)
        ->assertSee('dark:block', false);
});

it('en modo claro el Clásico usa solo el logo claro, aunque haya uno oscuro', function () {
    appearanceWithLogos('light');

    $this->get($this->url.'/')
        ->assertSee('storage/branding/claro.png', false)
        ->assertDontSee('storage/branding/oscuro.png', false)
        ->assertDontSee('dark:block', false);
});

it('en modo oscuro el Clásico usa solo el logo oscuro, o el claro si no hay', function () {
    appearanceWithLogos('dark');

    $this->get($this->url.'/')
        ->assertSee('storage/branding/oscuro.png', false)
        ->assertDontSee('storage/branding/claro.png', false)
        ->assertDontSee('dark:block', false);

    app(Appearance::class)->setFile('logo_dark', null);

    $this->get($this->url.'/')
        ->assertSee('storage/branding/claro.png', false)
        ->assertDontSee('storage/branding/oscuro.png', false);
});

// --- Pantalla de Apariencia ------------------------------------------------------

it('Apariencia muestra y guarda el modo de color', function () {
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));
    app(Appearance::class)->setMode('dark');

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(ManageAppearance::class)
        ->assertSet('data.mode', 'dark')
        ->assertSee('Modo de color de la landing')
        ->set('data.mode', 'light')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Appearance::class)->modeKey())->toBe('light');
});

it('Apariencia no acepta un modo que no existe', function () {
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(ManageAppearance::class)
        ->set('data.mode', 'sepia')
        ->call('save')
        ->assertHasFormErrors(['mode']);

    expect(app(Appearance::class)->modeKey())->toBe('auto');
});
