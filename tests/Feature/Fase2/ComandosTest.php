<?php

use App\Enums\Module;
use App\Facades\Modules;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    resetModules();
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);
});

it('modules:list muestra todos los módulos con su estado', function () {
    Modules::enable(Module::Stock);

    expect(Artisan::call('modules:list'))->toBe(0);

    $output = Artisan::output();

    expect($output)
        ->toContain('Cuenta corriente')
        ->toContain('Cuotas propias')
        ->toContain('Encendido')
        ->toContain('Apagado')
        ->toContain('GOOGLE_CLIENT_ID');
});

it('modules:enable enciende un módulo', function () {
    $this->artisan('modules:enable', ['key' => 'stock'])
        ->expectsOutputToContain('Módulo encendido: Stock')
        ->assertSuccessful();

    expect(Modules::enabled(Module::Stock))->toBeTrue();
});

it('modules:disable apaga un módulo', function () {
    Modules::enable(Module::Stock);

    $this->artisan('modules:disable', ['key' => 'stock'])
        ->expectsOutputToContain('Módulo apagado: Stock')
        ->assertSuccessful();

    expect(Modules::enabled(Module::Stock))->toBeFalse();
});

it('modules:enable falla con una clave inválida y lista las válidas', function () {
    $this->artisan('modules:enable', ['key' => 'no-existe'])
        ->expectsOutputToContain('current-account')
        ->assertFailed();
});

it('modules:enable sin clave ni --all falla', function () {
    $this->artisan('modules:enable')->assertFailed();
});

it('modules:disable falla con una clave inválida', function () {
    $this->artisan('modules:disable', ['key' => 'no-existe'])->assertFailed();
});

it('modules:enable falla si falta una dependencia', function () {
    $this->artisan('modules:enable', ['key' => 'installments'])
        ->expectsOutputToContain('primero hay que habilitar: Cuenta corriente')
        ->assertFailed();

    expect(Modules::enabled(Module::Installments))->toBeFalse();
});

it('modules:disable falla si otro módulo encendido lo usa', function () {
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);

    $this->artisan('modules:disable', ['key' => 'current-account'])
        ->expectsOutputToContain('lo usa Cuotas propias')
        ->assertFailed();

    expect(Modules::enabled(Module::CurrentAccount))->toBeTrue();
});

it('modules:enable falla con Google sin credenciales', function () {
    $this->artisan('modules:enable', ['key' => 'google-login'])
        ->expectsOutputToContain('GOOGLE_CLIENT_ID')
        ->assertFailed();
});

it('modules:enable --all enciende todo lo posible y avisa lo omitido', function () {
    $this->artisan('modules:enable', ['--all' => true])
        ->expectsOutputToContain('Encendido: Cuenta corriente')
        ->expectsOutputToContain('Encendido: Cuotas propias')
        ->expectsOutputToContain('Omitido: Login con Google')
        ->assertSuccessful();

    expect(Modules::enabled(Module::Installments))->toBeTrue()
        ->and(Modules::enabled(Module::GoogleLogin))->toBeFalse();
});
