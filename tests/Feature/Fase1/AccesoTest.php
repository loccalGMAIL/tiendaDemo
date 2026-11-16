<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->panelUrl = 'http://'.config('domains.panel');
});

it('define el acceso a cada panel según el rol', function (string $role, string $panel, bool $expected) {
    $user = User::factory()->{$role}()->create();

    expect($user->canAccessPanel(Filament::getPanel($panel)))->toBe($expected);
})->with([
    'superadmin entra a superadmin' => ['superadmin', 'superadmin', true],
    'superadmin no entra a admin' => ['superadmin', 'admin', false],
    'admin entra a admin' => ['admin', 'admin', true],
    'admin no entra a superadmin' => ['admin', 'superadmin', false],
    'vendedor entra a admin' => ['seller', 'admin', true],
    'vendedor no entra a superadmin' => ['seller', 'superadmin', false],
]);

it('un usuario inactivo no entra a ningún panel', function (string $role, string $panel) {
    $user = User::factory()->{$role}()->inactive()->create();

    expect($user->canAccessPanel(Filament::getPanel($panel)))->toBeFalse();
})->with([
    ['admin', 'admin'],
    ['seller', 'admin'],
    ['superadmin', 'superadmin'],
]);

it('un usuario inactivo no puede iniciar sesión', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $user = User::factory()->admin()->inactive()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('el login de superadmin con otro rol da el mismo error que con credenciales incorrectas', function () {
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));
    $admin = User::factory()->admin()->create();

    $wrongPassword = Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'incorrecta'])
        ->call('authenticate')
        ->errors()
        ->first('data.email');

    $otherRole = Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->errors()
        ->first('data.email');

    expect($otherRole)->not->toBeEmpty()->toBe($wrongPassword);
    $this->assertGuest();
});

it('el administrador entra al panel admin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->admin()->create();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin);
});

it('el vendedor no accede a Usuarios ni a Actividad', function (string $path) {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)->get($this->panelUrl.$path)->assertForbidden();
})->with(['/users', '/users/create', '/activity-logs']);

it('el administrador accede a Usuarios y a Actividad', function (string $path) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get($this->panelUrl.$path)->assertOk();
})->with(['/users', '/users/create', '/activity-logs']);

it('el panel superadmin solo es accesible para el superadmin', function () {
    $url = 'http://'.config('domains.superadmin').'/';

    $this->actingAs(User::factory()->admin()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->seller()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->superadmin()->create())->get($url)->assertOk();
});

it('el panel admin no es accesible para el superadmin', function () {
    $this->actingAs(User::factory()->superadmin()->create())
        ->get($this->panelUrl.'/')
        ->assertForbidden();
});
