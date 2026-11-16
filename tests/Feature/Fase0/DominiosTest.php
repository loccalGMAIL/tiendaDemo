<?php

beforeEach(function () {
    $this->withoutVite();

    $this->landing = 'http://'.config('domains.landing');
    $this->panel = 'http://'.config('domains.panel');
    $this->superadmin = 'http://'.config('domains.superadmin');
});

it('sirve la landing en su host', function () {
    $this->get($this->landing.'/')
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Ingresar al sistema');
});

it('sirve el login del panel admin en su host', function () {
    $this->get($this->panel.'/login')->assertOk();
});

it('sirve el login del panel superadmin en su host', function () {
    $this->get($this->superadmin.'/login')->assertOk();
});

it('registra cada panel en su propio host', function () {
    expect(route('filament.admin.auth.login'))->toStartWith($this->panel)
        ->and(route('filament.superadmin.auth.login'))->toStartWith($this->superadmin);
});

it('no expone el login de un panel en el host de otro', function () {
    $this->get($this->landing.'/login')->assertNotFound();
});

it('responde 404 en un host desconocido', function (string $path) {
    $this->get('http://desconocido.localhost'.$path)->assertNotFound();
    $this->get('http://127.0.0.1'.$path)->assertNotFound();
})->with(['/', '/login', '/up']);

it('el enlace de la landing apunta al panel conservando el puerto', function () {
    $this->get($this->landing.':8000/')
        ->assertOk()
        ->assertSee('http://'.config('domains.panel').':8000', false);

    $this->get($this->landing.'/')
        ->assertSee('href="http://'.config('domains.panel').'"', false);
});

it('detrás de un proxy con SSL los enlaces salen con https', function () {
    $this->get($this->landing.'/', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('href="https://'.config('domains.panel').'"', false);

    $this->get($this->panel.'/login', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertDontSee('http://'.config('domains.panel'), false);
});

it('el health check responde solo en el host del panel', function () {
    $this->get($this->panel.'/up')->assertOk();
    $this->get($this->landing.'/up')->assertNotFound();
});
