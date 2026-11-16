<?php

use App\Enums\Module;
use App\Facades\Modules;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\StockPage;
use Tests\Fixtures\StockResource;
use Tests\Fixtures\StockWidget;

beforeEach(function () {
    resetModules();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('un recurso del módulo desaparece con el módulo apagado', function () {
    expect(StockResource::canAccess())->toBeFalse()
        ->and(StockResource::shouldRegisterNavigation())->toBeFalse();

    Modules::enable(Module::Stock);

    expect(StockResource::canAccess())->toBeTrue()
        ->and(StockResource::shouldRegisterNavigation())->toBeTrue();
});

it('una página del módulo desaparece con el módulo apagado', function () {
    expect(StockPage::canAccess())->toBeFalse()
        ->and(StockPage::shouldRegisterNavigation())->toBeFalse();

    Modules::enable(Module::Stock);

    expect(StockPage::canAccess())->toBeTrue()
        ->and(StockPage::shouldRegisterNavigation())->toBeTrue();
});

it('un widget del módulo no se muestra con el módulo apagado', function () {
    expect(StockWidget::canView())->toBeFalse();

    Modules::enable(Module::Stock);

    expect(StockWidget::canView())->toBeTrue();
});

it('las rutas de recursos y páginas del módulo llevan el middleware del módulo', function () {
    $panel = Filament::getPanel('admin');

    expect(StockResource::getRouteMiddleware($panel))->toContain('module:stock')
        ->and(StockPage::getRouteMiddleware($panel))->toContain('module:stock');
});

it('una ruta con module:stock responde 404 apagado y 200 encendido', function () {
    Route::domain(config('domains.landing'))
        ->middleware('module:stock')
        ->get('/__sonda-stock', fn () => 'visible');

    $this->get('http://'.config('domains.landing').'/__sonda-stock')->assertNotFound();

    Modules::enable(Module::Stock);

    $this->get('http://'.config('domains.landing').'/__sonda-stock')->assertOk()->assertSee('visible');

    Modules::disable(Module::Stock);

    $this->get('http://'.config('domains.landing').'/__sonda-stock')->assertNotFound();
});

it('una clave de módulo inexistente en la ruta responde 404', function () {
    Route::domain(config('domains.landing'))
        ->middleware('module:no-existe')
        ->get('/__sonda-inexistente', fn () => 'visible');

    $this->get('http://'.config('domains.landing').'/__sonda-inexistente')->assertNotFound();
});

it('el bloque @module aparece solo con el módulo encendido', function () {
    $template = "@module('stock') CON-STOCK @endmodule\n@module('catalog') CON-CATALOGO @endmodule";

    expect(Blade::render($template))->not->toContain('CON-STOCK')->not->toContain('CON-CATALOGO');

    Modules::enable(Module::Stock);

    expect(Blade::render($template))->toContain('CON-STOCK')->not->toContain('CON-CATALOGO');
});

it('el bloque @module con una clave inexistente no se muestra', function () {
    expect(Blade::render("@module('no-existe') OCULTO @endmodule"))->not->toContain('OCULTO');
});

it('los campos de formulario de otro módulo se muestran solo con el módulo encendido', function () {
    $field = TextInput::make('min_stock')->visible(fn () => Modules::enabled(Module::Stock));

    expect($field->isVisible())->toBeFalse();

    Modules::enable(Module::Stock);

    expect($field->isVisible())->toBeTrue();
});

it('las tareas programadas de un módulo corren solo con el módulo encendido', function () {
    $event = app(Schedule::class)
        ->call(fn () => null)
        ->when(fn () => Modules::enabled(Module::Stock));

    expect($event->filtersPass(app()))->toBeFalse();

    Modules::enable(Module::Stock);

    expect($event->filtersPass(app()))->toBeTrue();
});

it('apagar un módulo no rompe a otro', function () {
    Modules::enable(Module::Stock);
    Modules::enable(Module::Catalog);

    Modules::disable(Module::Stock);

    expect(Modules::enabled(Module::Catalog))->toBeTrue();
});
