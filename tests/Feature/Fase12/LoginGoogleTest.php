<?php

use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Facades\Modules;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    resetModules();
    config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret', 'services.google.redirect' => null]);
    Modules::enable(Module::GoogleLogin);

    $this->panel = 'http://'.config('domains.panel');
    $this->loginUrl = $this->panel.'/login';
});

function fakeGoogle(string $id, string $email, bool $verified = true): void
{
    Socialite::fake('google', (new GoogleUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Persona de Google', 'email' => $email]));
}

// --- Ingreso ------------------------------------------------------------------------------------

it('el usuario existente entra al panel y queda en la actividad', function () {
    $user = User::factory()->admin()->create(['email' => 'ana@cliente.test']);
    fakeGoogle('g-1', 'ana@cliente.test');

    $this->get($this->panel.'/auth/google/callback')->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->google_id)->toBe('g-1')
        ->and(ActivityLog::where('event', ActivityEvent::Login->value)->where('user_id', $user->id)->count())->toBe(1);
});

it('el vendedor entra y puede abrir el panel', function () {
    $seller = User::factory()->seller()->create(['email' => 'luis@cliente.test']);
    fakeGoogle('g-2', 'luis@cliente.test');

    $this->get($this->panel.'/auth/google/callback')->assertRedirect();

    $this->assertAuthenticatedAs($seller);
    $this->get($this->panel.'/sales')->assertOk();
});

it('vuelve a la página que se pedía antes de ingresar', function () {
    User::factory()->admin()->create(['email' => 'ana@cliente.test']);
    fakeGoogle('g-3', 'ana@cliente.test');

    $this->withSession(['url.intended' => $this->panel.'/customers'])
        ->get($this->panel.'/auth/google/callback')
        ->assertRedirect($this->panel.'/customers');
});

// --- Rechazos -------------------------------------------------------------------------------------

it('los rechazos vuelven al login con el mensaje de credenciales incorrectas y sin sesión', function (string $case) {
    match ($case) {
        'inexistente' => null,
        'inactivo' => User::factory()->admin()->inactive()->create(['email' => 'x@cliente.test']),
        'superadmin' => User::factory()->superadmin()->create(['email' => 'x@cliente.test']),
        'vinculado a otra' => User::factory()->admin()->create(['email' => 'x@cliente.test', 'google_id' => 'otra']),
    };

    fakeGoogle('g-4', 'x@cliente.test');

    $this->get($this->panel.'/auth/google/callback')
        ->assertRedirect($this->loginUrl)
        ->assertSessionHas('google_login_error', trans('auth.failed'));

    $this->assertGuest();
    expect(User::where('google_id', 'g-4')->count())->toBe(0);
})->with(['inexistente', 'inactivo', 'superadmin', 'vinculado a otra']);

it('un correo sin verificar no entra', function () {
    User::factory()->admin()->create(['email' => 'ana@cliente.test']);
    fakeGoogle('g-5', 'ana@cliente.test', verified: false);

    $this->get($this->panel.'/auth/google/callback')->assertRedirect($this->loginUrl);

    $this->assertGuest();
});

it('el intento fallido queda en la actividad con el correo', function () {
    fakeGoogle('g-6', 'desconocido@gmail.test');

    $this->get($this->panel.'/auth/google/callback');

    $log = ActivityLog::where('event', ActivityEvent::LoginFailed->value)->firstOrFail();

    expect($log->description)->toBe('Intento de ingreso con Google fallido: desconocido@gmail.test')
        ->and($log->properties['email'])->toBe('desconocido@gmail.test')
        ->and($log->user_id)->toBeNull();
});

it('un intento sobre un SuperAdmin queda oculto al cliente como el resto de su actividad', function () {
    $superadmin = User::factory()->superadmin()->create(['email' => 'root@plataforma.test']);
    fakeGoogle('g-7', 'root@plataforma.test');

    $this->get($this->panel.'/auth/google/callback')->assertRedirect($this->loginUrl);

    expect(ActivityLog::where('event', ActivityEvent::LoginFailed->value)->where('user_id', $superadmin->id)->count())->toBe(1)
        ->and(ActivityLog::visibleToClient()->where('event', ActivityEvent::LoginFailed->value)->count())->toBe(0);
});

it('si el proveedor falla o se deniega el permiso vuelve al login con un aviso', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get($this->panel.'/auth/google/callback')
        ->assertRedirect($this->loginUrl)
        ->assertSessionHas('google_login_error', 'No pudimos completar el ingreso con Google.');

    $this->assertGuest();

    expect(ActivityLog::where('event', ActivityEvent::LoginFailed->value)->count())->toBe(1);
});

// --- Redirección a Google -----------------------------------------------------------------------------

it('redirige a Google pidiendo elegir la cuenta y con el callback de esta aplicación', function () {
    $location = (string) $this->get($this->panel.'/auth/google/redirect')->assertRedirect()->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://accounts.google.com/')
        ->and($query['client_id'])->toBe('client-id')
        ->and($query['prompt'])->toBe('select_account')
        ->and($query['redirect_uri'])->toBe(route('auth.google.callback'))
        ->and($query)->toHaveKey('state');
});

it('con GOOGLE_REDIRECT_URI configurada usa esa URI', function () {
    config(['services.google.redirect' => 'https://panel.negocio.test/auth/google/callback']);

    $location = (string) $this->get($this->panel.'/auth/google/redirect')->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('https://panel.negocio.test/auth/google/callback');
});

// --- Módulo --------------------------------------------------------------------------------------------------

it('con el módulo apagado las dos rutas no existen', function () {
    Modules::disable(Module::GoogleLogin);
    fakeGoogle('g-8', 'ana@cliente.test');

    $this->get($this->panel.'/auth/google/redirect')->assertNotFound();
    $this->get($this->panel.'/auth/google/callback')->assertNotFound();
});

it('el módulo no se puede encender sin credenciales', function () {
    Modules::disable(Module::GoogleLogin);
    config(['services.google.client_secret' => null]);

    expect(fn () => Modules::enable(Module::GoogleLogin))->toThrow(ValidationException::class, 'GOOGLE_CLIENT_ID');

    expect(Modules::enabled(Module::GoogleLogin))->toBeFalse();
});

// --- Botón en el login ------------------------------------------------------------------------------------------

it('el login del panel muestra el botón solo con el módulo encendido', function () {
    $this->get($this->panel.'/login')->assertOk()->assertSee('Ingresar con Google')->assertSee(route('auth.google.redirect'));

    Modules::disable(Module::GoogleLogin);

    $this->get($this->panel.'/login')->assertOk()->assertDontSee('Ingresar con Google');
});

it('el login de la plataforma nunca lleva el botón', function () {
    $this->get('http://'.config('domains.superadmin').'/login')->assertOk()->assertDontSee('Ingresar con Google');
});

it('el login muestra el aviso del ingreso rechazado', function () {
    $this->withSession(['google_login_error' => trans('auth.failed')])
        ->get($this->panel.'/login')
        ->assertSee(trans('auth.failed'));

    // Sin módulo no se muestra ni el aviso.
    Modules::disable(Module::GoogleLogin);

    $this->withSession(['google_login_error' => 'Aviso viejo'])->get($this->panel.'/login')->assertDontSee('Aviso viejo');
});
