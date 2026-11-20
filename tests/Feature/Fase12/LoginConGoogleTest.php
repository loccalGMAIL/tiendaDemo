<?php

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->withoutVite();
    resetModules();

    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
    ]);

    $this->panel = 'http://'.config('domains.panel');
    $this->superadmin = 'http://'.config('domains.superadmin');
});

function fakeGoogleUser(string $id, string $email): void
{
    $googleUser = (new SocialiteUser)->map(['id' => $id, 'name' => 'Persona', 'email' => $email]);

    Socialite::fake('google', $googleUser);
}

it('no se habilita sin credenciales en el .env', function () {
    config(['services.google.client_id' => null]);

    expect(Module::GoogleLogin->missingConfiguration())->not->toBeNull();
});

it('con el módulo apagado las rutas responden 404 y no hay botón', function () {
    $this->get($this->panel.'/auth/google/redirect')->assertNotFound();
    $this->get($this->panel.'/auth/google/callback')->assertNotFound();
    $this->get($this->panel.'/login')->assertOk()->assertDontSee('Ingresar con Google');
});

it('con el módulo encendido muestra el botón en el login del panel admin y no en el de superadmin', function () {
    Modules::enable(Module::GoogleLogin);

    $this->get($this->panel.'/login')->assertOk()->assertSee('Ingresar con Google');
    $this->get($this->superadmin.'/login')->assertOk()->assertDontSee('Ingresar con Google');
});

it('redirige a Google', function () {
    Modules::enable(Module::GoogleLogin);
    Socialite::fake('google');

    $this->get($this->panel.'/auth/google/redirect')->assertRedirect('https://socialite.fake/google/authorize');
});

it('entra un usuario existente por google_id', function () {
    Modules::enable(Module::GoogleLogin);
    $user = User::factory()->admin()->create(['google_id' => 'g-123']);
    fakeGoogleUser('g-123', 'otro-correo@example.com');

    $this->get($this->panel.'/auth/google/callback')->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('entra por email y guarda el google_id', function () {
    Modules::enable(Module::GoogleLogin);
    $user = User::factory()->seller()->create(['email' => 'vendedora@example.com']);
    fakeGoogleUser('g-456', 'vendedora@example.com');

    $this->get($this->panel.'/auth/google/callback')->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-456');
});

it('no crea usuarios desconocidos', function () {
    Modules::enable(Module::GoogleLogin);
    $before = User::count();
    fakeGoogleUser('g-999', 'desconocida@example.com');

    $this->get($this->panel.'/auth/google/callback')
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('google_login_error');

    $this->assertGuest();
    expect(User::count())->toBe($before);
});

it('rechaza a un usuario inactivo', function () {
    Modules::enable(Module::GoogleLogin);
    User::factory()->admin()->create(['email' => 'baja@example.com', 'active' => false]);
    fakeGoogleUser('g-777', 'baja@example.com');

    $this->get($this->panel.'/auth/google/callback')->assertSessionHas('google_login_error');

    $this->assertGuest();
});

it('rechaza al SuperAdmin', function () {
    Modules::enable(Module::GoogleLogin);
    User::factory()->superAdmin()->create(['email' => 'super@example.com']);
    fakeGoogleUser('g-888', 'super@example.com');

    $this->get($this->panel.'/auth/google/callback')->assertSessionHas('google_login_error');

    $this->assertGuest();
});

it('no entra por email a quien ya está vinculado con otra cuenta de Google', function () {
    Modules::enable(Module::GoogleLogin);
    User::factory()->admin()->create(['email' => 'vinculada@example.com', 'google_id' => 'g-original']);
    fakeGoogleUser('g-otra', 'vinculada@example.com');

    $this->get($this->panel.'/auth/google/callback')->assertSessionHas('google_login_error');

    $this->assertGuest();
});
