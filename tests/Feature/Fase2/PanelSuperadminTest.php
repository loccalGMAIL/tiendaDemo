<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Superadmin\Pages\ManageModules;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    resetModules();
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $this->url = 'http://'.config('domains.superadmin').'/modules';
});

function superadminPage(): Testable
{
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    return Livewire::actingAs(User::factory()->superadmin()->create())->test(ManageModules::class);
}

it('solo el SuperAdmin entra a Módulos', function () {
    $this->actingAs(User::factory()->admin()->create())->get($this->url)->assertForbidden();
    $this->actingAs(User::factory()->seller()->create())->get($this->url)->assertForbidden();
    $this->actingAs(User::factory()->superadmin()->create())->get($this->url)->assertOk()->assertSee('Módulos');
});

it('un visitante sin sesión no ve Módulos', function () {
    $this->get($this->url)->assertRedirect();
});

it('el panel admin no tiene la página de módulos', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('http://'.config('domains.panel').'/modules')
        ->assertNotFound();
});

it('lista todos los módulos con su descripción', function () {
    $this->actingAs(User::factory()->superadmin()->create());

    $html = $this->get($this->url)->assertOk()->getContent();

    foreach (Module::cases() as $module) {
        expect($html)->toContain($module->getLabel());
    }
});

it('enciende y apaga un módulo desde su interruptor', function () {
    superadminPage()
        ->set('data.modules.Stock.enabled', true)
        ->assertNotified();

    expect(Modules::enabled(Module::Stock))->toBeTrue();

    superadminPage()
        ->set('data.modules.Stock.enabled', false)
        ->assertNotified();

    expect(Modules::enabled(Module::Stock))->toBeFalse();
});

it('no deja encender Cuotas propias sin Cuenta corriente', function () {
    $page = superadminPage();

    expect($page->instance()->blockedReason(Module::Installments))->toContain('Requiere Cuenta corriente');

    $page->set('data.modules.Installments.enabled', true);

    expect(Modules::enabled(Module::Installments))->toBeFalse();
});

it('deja encender Cuotas propias una vez encendida Cuenta corriente', function () {
    superadminPage()->set('data.modules.CurrentAccount.enabled', true);
    superadminPage()->set('data.modules.Installments.enabled', true);

    expect(Modules::enabled(Module::Installments))->toBeTrue();
});

it('no deja apagar Cuenta corriente mientras Cuotas propias esté encendido', function () {
    Modules::enable(Module::CurrentAccount);
    Modules::enable(Module::Installments);

    $page = superadminPage();

    expect($page->instance()->blockedReason(Module::CurrentAccount))->toContain('Lo usa Cuotas propias');

    $page->set('data.modules.CurrentAccount.enabled', false);

    expect(Modules::enabled(Module::CurrentAccount))->toBeTrue();
});

it('Login con Google queda bloqueado sin credenciales', function () {
    $page = superadminPage();

    expect($page->instance()->blockedReason(Module::GoogleLogin))->toContain('GOOGLE_CLIENT_ID');

    $page->set('data.modules.GoogleLogin.enabled', true);

    expect(Modules::enabled(Module::GoogleLogin))->toBeFalse();
});

it('Login con Google se puede encender con credenciales', function () {
    config()->set('services.google.client_id', 'id');
    config()->set('services.google.client_secret', 'secret');

    $page = superadminPage();

    expect($page->instance()->blockedReason(Module::GoogleLogin))->toBeNull();

    $page->set('data.modules.GoogleLogin.enabled', true);

    expect(Modules::enabled(Module::GoogleLogin))->toBeTrue();
});

it('muestra el estado real de cada módulo al abrir la página', function () {
    Modules::enable(Module::Catalog);

    $page = superadminPage();

    expect($page->get('data.modules.Catalog.enabled'))->toBeTrue()
        ->and($page->get('data.modules.Stock.enabled'))->toBeFalse();
});

it('guarda los ajustes de los módulos', function () {
    superadminPage()
        ->fillForm([
            'modules' => [
                'Stock' => ['settings' => ['on_no_stock' => 'block']],
                'Installments' => ['settings' => ['due_rule' => 'fixed_day', 'fixed_day' => 20]],
                'Catalog' => ['settings' => ['show_sold_out' => false]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Modules::setting(Module::Stock, 'on_no_stock'))->toBe('block')
        ->and(Modules::setting(Module::Installments, 'fixed_day'))->toBe(20)
        ->and(Modules::setting(Module::Catalog, 'show_sold_out'))->toBeFalse();
});

it('valida los ajustes: el día fijo va de 1 a 28', function () {
    superadminPage()
        ->fillForm([
            'modules' => ['Installments' => ['settings' => ['due_rule' => 'fixed_day', 'fixed_day' => 31]]],
        ])
        ->call('save')
        ->assertHasFormErrors(['modules.Installments.settings.fixed_day']);

    expect(Modules::setting(Module::Installments, 'fixed_day'))->toBe(10);
});
