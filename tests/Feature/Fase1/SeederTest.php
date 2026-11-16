<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InitialUsersSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('platform.admin', ['name' => 'Dueño', 'email' => 'dueno@example.com', 'password' => 'clave-admin-1']);
    config()->set('platform.superadmin', ['name' => 'Plataforma', 'email' => 'plataforma@example.com', 'password' => 'clave-super-1']);
});

it('crea el administrador y el superadmin', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'dueno@example.com')->firstOrFail();
    $super = User::where('email', 'plataforma@example.com')->firstOrFail();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->active)->toBeTrue()
        ->and($admin->is_default_seller)->toBeTrue()
        ->and(Hash::check('clave-admin-1', $admin->password))->toBeTrue()
        ->and($super->role)->toBe(Role::SuperAdmin);
});

it('se puede correr dos veces sin duplicar', function () {
    $this->seed(InitialUsersSeeder::class);
    $this->seed(InitialUsersSeeder::class);

    expect(User::count())->toBe(2);
});

it('no pisa una contraseña ya cambiada pero sí restituye rol y estado', function () {
    $this->seed(InitialUsersSeeder::class);

    User::where('email', 'dueno@example.com')->update(['password' => Hash::make('nueva-clave'), 'active' => false]);

    $this->seed(InitialUsersSeeder::class);

    $admin = User::where('email', 'dueno@example.com')->firstOrFail();
    expect(Hash::check('nueva-clave', $admin->password))->toBeTrue()
        ->and($admin->active)->toBeTrue();
});

it('omite al usuario cuyo email no está configurado', function () {
    config()->set('platform.superadmin', ['name' => null, 'email' => null, 'password' => null]);

    $this->seed(InitialUsersSeeder::class);

    expect(User::count())->toBe(1);
});
