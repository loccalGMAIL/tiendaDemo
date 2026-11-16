<?php

use App\Services\Appearance;
use App\Services\Contrast;
use App\Services\Settings;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// --- Configuración de temas -----------------------------------------------

it('hay al menos dos temas reales', function () {
    expect(array_keys(config('themes.themes')))->toBe(['clasico', 'boutique'])
        ->and(config('themes.default'))->toBe('clasico');
});

it('cada tema tiene vistas, hoja de estilos, vista previa y recursos gráficos', function (string $key) {
    $theme = config("themes.themes.{$key}");
    $views = resource_path('views/'.str_replace('.', '/', $theme['views']));

    foreach (['index', 'header', 'hero', 'value', 'catalog', 'faq', 'contact', 'footer'] as $file) {
        expect(file_exists("{$views}/{$file}.blade.php"))->toBeTrue("falta {$file} en {$key}");
    }

    expect(file_exists(base_path($theme['css'])))->toBeTrue()
        ->and(file_exists(public_path($theme['preview'])))->toBeTrue()
        ->and($theme['assets'])->not->toBeEmpty()
        ->and(count($theme['palettes']))->toBeGreaterThanOrEqual(2)
        ->and($theme['font'])->toHaveKeys(['body', 'heading'])
        ->and($theme['name'])->not->toBeEmpty()
        ->and($theme['description'])->not->toBeEmpty();
})->with(['clasico', 'boutique']);

it('todos los temas se compilan siempre (están en la configuración de Vite)', function () {
    $vite = file_get_contents(base_path('vite.config.js'));

    foreach (config('themes.themes') as $theme) {
        expect($vite)->toContain($theme['css']);
    }
});

it('cada tema es distinto: vistas y hoja de estilos propias', function () {
    $themes = config('themes.themes');

    expect($themes['clasico']['views'])->not->toBe($themes['boutique']['views'])
        ->and($themes['clasico']['css'])->not->toBe($themes['boutique']['css']);
});

it('las combinaciones de colores tienen todos sus colores y un contraste legible', function (string $themeKey, string $paletteKey) {
    $palette = config("themes.themes.{$themeKey}.palettes.{$paletteKey}");

    foreach (['name', 'primary', 'on_primary', 'accent', 'surface', 'text', 'surface_dark', 'text_dark'] as $token) {
        expect($palette)->toHaveKey($token);
    }

    // Texto sobre el color de marca, sobre el fondo claro y sobre el fondo oscuro.
    expect(Contrast::ratio($palette['on_primary'], $palette['primary']))->toBeGreaterThanOrEqual(Contrast::MINIMUM)
        ->and(Contrast::ratio($palette['text'], $palette['surface']))->toBeGreaterThanOrEqual(Contrast::MINIMUM)
        ->and(Contrast::ratio($palette['text_dark'], $palette['surface_dark']))->toBeGreaterThanOrEqual(Contrast::MINIMUM);
})->with(function () {
    // Los datasets se evalúan antes de arrancar la aplicación: se lee el archivo de configuración.
    $themes = require __DIR__.'/../../../config/themes.php';

    foreach ($themes['themes'] as $themeKey => $theme) {
        foreach (array_keys($theme['palettes']) as $paletteKey) {
            yield "{$themeKey}/{$paletteKey}" => [$themeKey, $paletteKey];
        }
    }
});

// --- Contraste ------------------------------------------------------------

it('calcula el contraste según WCAG', function () {
    expect(Contrast::ratio('#000000', '#ffffff'))->toEqualWithDelta(21.0, 0.01)
        ->and(Contrast::ratio('#ffffff', '#ffffff'))->toEqualWithDelta(1.0, 0.001)
        ->and(Contrast::ratio('#ffffff', '#000000'))->toEqualWithDelta(21.0, 0.01)
        // #767676 sobre blanco es el gris más claro que alcanza AA.
        ->and(Contrast::ratio('#767676', '#ffffff'))->toEqualWithDelta(4.54, 0.02)
        ->and(Contrast::ratio('#fff', '#000'))->toEqualWithDelta(21.0, 0.01)
        ->and(Contrast::ratio('#ffff00', '#ffffff'))->toBeLessThan(Contrast::MINIMUM);
});

it('rechaza colores hexadecimales inválidos', function (string $color) {
    expect(fn () => Contrast::ratio($color, '#ffffff'))->toThrow(InvalidArgumentException::class);
})->with(['rojo', '#12345', '#gggggg', '']);

// --- Ajustes --------------------------------------------------------------

it('los ajustes se guardan, se leen y se olvidan', function () {
    $settings = app(Settings::class);

    expect($settings->get('clave', 'por defecto'))->toBe('por defecto');

    $settings->set('clave', ['a' => 1, 'b' => ['c' => 'd']]);
    expect($settings->get('clave'))->toBe(['a' => 1, 'b' => ['c' => 'd']]);

    $settings->set('clave', 'otro');
    expect($settings->get('clave'))->toBe('otro');

    $settings->forget('clave');
    expect($settings->get('clave', 'por defecto'))->toBe('por defecto');
});

it('sin la tabla de ajustes se usan los valores por defecto', function () {
    Schema::drop('settings');
    Cache::flush();

    expect(app(Settings::class)->get('clave', 'por defecto'))->toBe('por defecto')
        ->and(app(Appearance::class)->themeKey())->toBe('clasico');
});

// --- Apariencia -----------------------------------------------------------

it('por defecto usa el primer tema y su primera combinación', function () {
    $appearance = app(Appearance::class);

    expect($appearance->themeKey())->toBe('clasico')
        ->and($appearance->paletteKey())->toBe('ambar')
        ->and($appearance->palette()['primary'])->toBe('#b45309')
        ->and(array_keys($appearance->palettes()))->toBe(['ambar', 'oceano', 'bosque', 'rosa']);
});

it('al cambiar de tema la combinación vuelve a la primera del tema nuevo', function () {
    $appearance = app(Appearance::class);
    $appearance->setPalette('rosa');

    $appearance->setTheme('boutique');

    expect($appearance->themeKey())->toBe('boutique')
        ->and($appearance->paletteKey())->toBe('noche')
        ->and(array_keys($appearance->palettes()))->toBe(['noche', 'vino', 'salvia', 'tinta']);
});

it('rechaza un tema inexistente y una combinación de otro tema', function () {
    $appearance = app(Appearance::class);

    expect(fn () => $appearance->setTheme('inventado'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $appearance->setPalette('noche'))->toThrow(InvalidArgumentException::class);

    $appearance->setTheme('boutique');
    expect(fn () => $appearance->setPalette('oceano'))->toThrow(InvalidArgumentException::class);

    expect($appearance->themeKey())->toBe('boutique')->and($appearance->paletteKey())->toBe('noche');
});

it('si lo guardado ya no es válido vuelve a los valores por defecto', function () {
    app(Settings::class)->set('appearance.theme', 'borrado');
    app(Settings::class)->set('appearance.palette', 'borrada');

    expect(app(Appearance::class)->themeKey())->toBe('clasico')
        ->and(app(Appearance::class)->paletteKey())->toBe('ambar');

    app(Settings::class)->set('appearance.theme', 'boutique');
    app(Settings::class)->set('appearance.palette', 'oceano');

    expect(app(Appearance::class)->paletteKey())->toBe('noche');
});

it('las variables CSS llevan los colores de la combinación y la tipografía del tema', function () {
    $appearance = app(Appearance::class);
    $appearance->setTheme('boutique');
    $appearance->setPalette('vino');

    $css = $appearance->cssVariables();

    expect($css)->toContain('--brand:#7f1d1d')
        ->toContain('--on-brand:#ffffff')
        ->toContain('--surface:#fbf6f5')
        ->toContain('--text:#2a1515')
        ->toContain('--font-heading:Georgia')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('--surface:#1c0f0f');
});

it('los colores de Filament salen del color de marca de la combinación', function () {
    $appearance = app(Appearance::class);

    expect($appearance->filamentColors())->toBe(['primary' => Color::hex('#b45309')]);

    $appearance->setPalette('oceano');

    expect($appearance->filamentColors())->toBe(['primary' => Color::hex('#0369a1')]);
});

// --- Archivos de marca ----------------------------------------------------

it('los archivos de marca se guardan, se usan y se quitan', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'png');
    Storage::disk('public')->put('branding/dark.png', 'png');

    $appearance = app(Appearance::class);

    expect($appearance->logoUrl('light'))->toBeNull()->and($appearance->faviconUrl())->toBeNull();

    $appearance->setFile('logo_light', 'branding/logo.png');
    $appearance->setFile('logo_dark', 'branding/dark.png');

    expect($appearance->logoUrl('light'))->toEndWith('/storage/branding/logo.png')
        ->and($appearance->logoUrl('dark'))->toEndWith('/storage/branding/dark.png')
        // El acceso usa el logo claro si no hay uno propio.
        ->and($appearance->logoUrl('login'))->toEndWith('/storage/branding/logo.png');

    $appearance->setFile('logo_login', 'branding/dark.png');
    expect($appearance->logoUrl('login'))->toEndWith('/storage/branding/dark.png');

    $appearance->setFile('logo_light', null);
    expect($appearance->logoUrl('light'))->toBeNull();
});

it('reemplazar o quitar un archivo borra el anterior', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/uno.png', 'a');
    Storage::disk('public')->put('branding/dos.png', 'b');
    $appearance = app(Appearance::class);

    $appearance->setFile('favicon', 'branding/uno.png');
    $appearance->setFile('favicon', 'branding/dos.png');

    Storage::disk('public')->assertMissing('branding/uno.png');
    Storage::disk('public')->assertExists('branding/dos.png');

    $appearance->setFile('favicon', null);

    Storage::disk('public')->assertMissing('branding/dos.png');
    expect($appearance->files())->toBe([]);
});

it('un archivo que ya no existe en el disco no se usa', function () {
    Storage::fake('public');
    app(Appearance::class)->setFile('logo_light', 'branding/fantasma.png');

    expect(app(Appearance::class)->logoUrl('light'))->toBeNull();
});

it('rechaza archivos que no son de la marca', function () {
    expect(fn () => app(Appearance::class)->setFile('otro', 'branding/x.png'))->toThrow(InvalidArgumentException::class);
});

it('los recursos gráficos son por tema', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/hero.jpg', 'a');
    $appearance = app(Appearance::class);

    $appearance->setFile($appearance->assetSlot('hero'), 'branding/hero.jpg');

    expect($appearance->assetSlot('hero'))->toBe('asset:clasico:hero')
        ->and($appearance->assetUrl('hero'))->toEndWith('/storage/branding/hero.jpg');

    $appearance->setTheme('boutique');

    expect($appearance->assetUrl('hero'))->toBeNull();

    $appearance->setTheme('clasico');

    expect($appearance->assetUrl('hero'))->toEndWith('/storage/branding/hero.jpg');
});
