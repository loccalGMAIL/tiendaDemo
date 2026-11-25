<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\ModuleState;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    resetModules();
});

it('arranca con todos los módulos apagados', function () {
    foreach (Module::cases() as $module) {
        expect(Modules::enabled($module))->toBeFalse();
    }
});

it('enciende y apaga un módulo y registra cuándo se encendió', function () {
    Modules::enable(Module::Stock);

    expect(Modules::enabled(Module::Stock))->toBeTrue();

    $state = ModuleState::where('key', 'stock')->firstOrFail();
    expect($state->enabled)->toBeTrue()
        ->and($state->enabled_at)->not->toBeNull();

    Modules::disable(Module::Stock);

    expect(Modules::enabled(Module::Stock))->toBeFalse()
        ->and(ModuleState::where('key', 'stock')->first()->enabled_at)->toBeNull();
});

it('encender un módulo ya encendido no cambia nada', function () {
    Modules::enable(Module::Stock);
    $enabledAt = ModuleState::where('key', 'stock')->first()->enabled_at;

    $this->travel(5)->minutes();
    Modules::enable(Module::Stock);

    expect(ModuleState::where('key', 'stock')->first()->enabled_at->equalTo($enabledAt))->toBeTrue()
        ->and(ModuleState::count())->toBe(1);
});

it('apagar un módulo no borra sus ajustes', function () {
    Modules::enable(Module::Stock);
    Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block']);

    Modules::disable(Module::Stock);

    expect(Modules::setting(Module::Stock, 'on_no_stock'))->toBe('block');
});

it('el estado cacheado se actualiza apenas cambia', function () {
    expect(Modules::enabled(Module::Catalog))->toBeFalse();

    Modules::enable(Module::Catalog);
    expect(Modules::enabled(Module::Catalog))->toBeTrue();

    Modules::disable(Module::Catalog);
    expect(Modules::enabled(Module::Catalog))->toBeFalse();
});

it('no enciende un módulo sin los que requiere', function () {
    expect(fn () => Modules::enable(Module::Installments))
        ->toThrow(ValidationException::class, 'primero hay que habilitar: Cuenta corriente');

    expect(Modules::enabled(Module::Installments))->toBeFalse();
});

it('enciende un módulo cuando ya tiene lo que requiere', function () {
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);

    expect(Modules::enabled(Module::Installments))->toBeTrue();
});

it('no apaga un módulo del que depende otro encendido', function () {
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);

    expect(fn () => Modules::disable(Module::CurrentAccount))
        ->toThrow(ValidationException::class, 'lo usa Cuotas propias');

    expect(Modules::enabled(Module::CurrentAccount))->toBeTrue();

    Modules::disable(Module::Installments);
    Modules::disable(Module::CurrentAccount);

    expect(Modules::enabled(Module::CurrentAccount))->toBeFalse();
});

it('declara las dependencias en ambos sentidos', function () {
    expect(Module::Installments->requires())->toBe([Module::CurrentAccount])
        ->and(Module::CurrentAccount->dependents())->toBe([Module::Installments])
        ->and(Module::Stock->requires())->toBe([])
        ->and(Module::Stock->dependents())->toBe([]);
});

it('en el orden de los casos cada módulo viene después de los que requiere', function () {
    $position = array_flip(array_map(fn (Module $m) => $m->value, Module::cases()));

    foreach (Module::cases() as $module) {
        foreach ($module->requires() as $required) {
            expect($position[$required->value])->toBeLessThan($position[$module->value]);
        }
    }
});

it('Login con Google no se enciende sin credenciales', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    expect(Module::GoogleLogin->missingConfiguration())->toContain('GOOGLE_CLIENT_ID');
    expect(fn () => Modules::enable(Module::GoogleLogin))
        ->toThrow(ValidationException::class, 'GOOGLE_CLIENT_ID');
    expect(Modules::enabled(Module::GoogleLogin))->toBeFalse();
});

it('Login con Google se enciende con credenciales', function () {
    config()->set('services.google.client_id', 'id');
    config()->set('services.google.client_secret', 'secret');

    expect(Module::GoogleLogin->missingConfiguration())->toBeNull();

    Modules::enable(Module::GoogleLogin);

    expect(Modules::enabled(Module::GoogleLogin))->toBeTrue();
});

it('los ajustes parten de los valores por defecto y se pueden cambiar', function () {
    expect(Modules::settings(Module::Installments))->toBe(['due_rule' => 'fixed_day', 'fixed_day' => 10])
        ->and(Modules::setting(Module::Installments, 'fixed_day'))->toBe(10)
        ->and(Modules::setting(Module::Installments, 'inexistente', 'x'))->toBe('x');

    Modules::updateSettings(Module::Installments, ['fixed_day' => 15]);

    expect(Modules::setting(Module::Installments, 'fixed_day'))->toBe(15)
        ->and(Modules::setting(Module::Installments, 'due_rule'))->toBe('fixed_day');
});

it('ignora los ajustes que el módulo no declara', function () {
    Modules::updateSettings(Module::Stock, ['on_no_stock' => 'block', 'otra_cosa' => 'x']);

    expect(Modules::settings(Module::Stock))->toBe(['on_no_stock' => 'block'])
        ->and(ModuleState::where('key', 'stock')->first()->settings)->toBe(['on_no_stock' => 'block']);
});

it('un ajuste en false se devuelve como false y no como el valor por defecto', function () {
    Modules::updateSettings(Module::Catalog, ['show_sold_out' => false]);

    expect(Modules::setting(Module::Catalog, 'show_sold_out', true))->toBeFalse();
});

it('enableAll enciende en orden de dependencia y omite lo que no se puede', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $result = Modules::enableAll();

    expect(array_map(fn (Module $m) => $m->value, $result['enabled']))->toBe([
        'current-account', 'installments', 'variants', 'purchases', 'stock', 'reports', 'catalog', 'custom-colors', 'barcodes', 'point-of-sale', 'cash-control',
    ])
        ->and(array_keys($result['skipped']))->toBe(['google-login'])
        ->and(Modules::enabled(Module::Installments))->toBeTrue()
        ->and(Modules::enabled(Module::GoogleLogin))->toBeFalse();

    expect(Modules::enableAll())->toBe(['enabled' => [], 'skipped' => ['google-login' => Module::GoogleLogin->missingConfiguration()]]);
});

it('cada módulo tiene etiqueta y descripción', function () {
    foreach (Module::cases() as $module) {
        expect($module->getLabel())->not->toBeEmpty()
            ->and($module->getDescription())->not->toBeEmpty();
    }
});
