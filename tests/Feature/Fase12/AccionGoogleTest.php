<?php

use App\Actions\Auth\FindUserForGoogleLogin;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

function googleAccount(string $id, string $email, bool|string|null $verified = true): GoogleUser
{
    return (new GoogleUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Persona de Google', 'email' => $email]);
}

function expectGoogleRejected(GoogleUser $google): void
{
    try {
        app(FindUserForGoogleLogin::class)->handle($google);
    } catch (ValidationException $e) {
        // Siempre el mismo mensaje que las credenciales incorrectas: no revela quién existe.
        expect($e->errors())->toHaveKey('email')
            ->and($e->errors()['email'][0])->toBe(trans('auth.failed'));

        return;
    }

    throw new RuntimeException('Se esperaba que el ingreso con Google fuera rechazado.');
}

// --- Entra quien existe ------------------------------------------------------------------------

it('entra por google_id', function () {
    $user = User::factory()->admin()->create(['google_id' => 'g-100']);

    expect(app(FindUserForGoogleLogin::class)->handle(googleAccount('g-100', 'otro-correo@gmail.test'))->is($user))->toBeTrue();
});

it('entra por correo, sin distinguir mayúsculas, y guarda el google_id', function () {
    $user = User::factory()->admin()->create(['email' => 'Marta@Cliente.test']);

    $found = app(FindUserForGoogleLogin::class)->handle(googleAccount('g-200', 'marta@cliente.test'));

    expect($found->is($user))->toBeTrue()->and($user->refresh()->google_id)->toBe('g-200');
});

it('una vez vinculado entra por google_id aunque el correo ya no coincida', function () {
    $user = User::factory()->admin()->create(['email' => 'marta@cliente.test']);
    app(FindUserForGoogleLogin::class)->handle(googleAccount('g-300', 'marta@cliente.test'));

    $user->update(['email' => 'marta.nueva@cliente.test']);

    // Aunque ahora el correo de Google no esté verificado, la cuenta ya vinculada es la que manda.
    expect(app(FindUserForGoogleLogin::class)->handle(googleAccount('g-300', 'marta@cliente.test', false))->is($user))->toBeTrue();
});

it('el vendedor también entra', function () {
    $seller = User::factory()->seller()->create(['email' => 'luis@cliente.test']);

    expect(app(FindUserForGoogleLogin::class)->handle(googleAccount('g-400', 'luis@cliente.test'))->is($seller))->toBeTrue();
});

it('acepta el correo verificado que Google manda como texto', function () {
    User::factory()->admin()->create(['email' => 'ana@cliente.test']);

    expect(app(FindUserForGoogleLogin::class)->handle(googleAccount('g-500', 'ana@cliente.test', 'true'))->email)->toBe('ana@cliente.test');
});

// --- No registra y rechaza con el mismo mensaje ---------------------------------------------------

it('no crea usuarios', function () {
    User::factory()->admin()->create();

    expectGoogleRejected(googleAccount('g-600', 'desconocido@gmail.test'));

    expect(User::count())->toBe(1);
});

it('rechaza al inexistente, al inactivo y al SuperAdmin con el mismo mensaje', function (string $state) {
    $user = match ($state) {
        'inactivo' => User::factory()->admin()->inactive()->create(['email' => 'x@cliente.test']),
        'vendedor inactivo' => User::factory()->seller()->inactive()->create(['email' => 'x@cliente.test']),
        'superadmin' => User::factory()->superadmin()->create(['email' => 'x@cliente.test']),
        default => null,
    };

    expectGoogleRejected(googleAccount('g-700', 'x@cliente.test'));

    expect($user?->refresh()->google_id)->toBeNull();
})->with(['inexistente', 'inactivo', 'vendedor inactivo', 'superadmin']);

it('un usuario inactivo o un SuperAdmin ya vinculados tampoco entran', function (string $factoryState) {
    User::factory()->{$factoryState}()->create(['google_id' => 'g-710']);

    expectGoogleRejected(googleAccount('g-710', 'cualquiera@gmail.test'));
})->with(['inactive', 'superadmin']);

it('rechaza un correo que Google no verificó y no lo vincula', function (bool|string|null $verified) {
    $user = User::factory()->admin()->create(['email' => 'ana@cliente.test']);

    expectGoogleRejected(googleAccount('g-800', 'ana@cliente.test', $verified));

    expect($user->refresh()->google_id)->toBeNull();
})->with([false, 'false', null]);

it('no pisa una cuenta ya vinculada a otra de Google', function () {
    $user = User::factory()->admin()->create(['email' => 'ana@cliente.test', 'google_id' => 'g-AAA']);

    expectGoogleRejected(googleAccount('g-BBB', 'ana@cliente.test'));

    expect($user->refresh()->google_id)->toBe('g-AAA');
});

it('rechaza una cuenta de Google sin id o sin correo', function () {
    User::factory()->admin()->create(['email' => 'ana@cliente.test']);

    expectGoogleRejected(googleAccount('', 'ana@cliente.test'));
    expectGoogleRejected(googleAccount('g-900', ''));
});
