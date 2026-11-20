<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->landing = 'http://'.config('domains.landing');
    $this->panel = 'http://'.config('domains.panel');
    $this->superadmin = 'http://'.config('domains.superadmin');
});

it('toda respuesta web lleva las cabeceras de seguridad', function (string $host, string $path) {
    $response = $this->get($this->{$host}.$path);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
})->with([
    'landing' => ['landing', '/'],
    'login del panel' => ['panel', '/login'],
    'login de la plataforma' => ['superadmin', '/login'],
    'salud' => ['panel', '/up'],
]);

it('las pantallas del panel con sesión también las llevan', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get($this->panel.'/')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('las respuestas de error también las llevan', function () {
    $this->get($this->landing.'/producto/no-existe')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('HSTS solo se manda cuando la petición llegó por HTTPS', function () {
    $this->get($this->landing.'/')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://'.config('domains.landing').'/')->assertHeader('Strict-Transport-Security', 'max-age=15552000');
});

it('no pisa una cabecera que la respuesta ya traía', function () {
    // El middleware solo agrega las que faltan: una respuesta con su propia política la conserva.
    $middleware = new SecurityHeaders;
    $request = Request::create('http://tiendademo.localhost/x');

    $response = $middleware->handle($request, fn () => response('ok', 200, ['X-Frame-Options' => 'DENY']));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('el catálogo público limita las peticiones por IP', function () {
    Modules::enable(Module::Catalog);
    $url = 'http://'.config('domains.landing').'/catalogo?q=remera';

    for ($i = 0; $i < 120; $i++) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertStatus(429);
});
