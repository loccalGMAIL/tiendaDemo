<?php

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Filament\Resources\Users\Pages\CreateUser as CreateUserPage;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('solo un usuario puede ser vendedor por defecto', function () {
    $first = app(CreateUser::class)->handle([
        'name' => 'Primero', 'email' => 'a@example.com', 'password' => 'password123',
        'role' => 'seller', 'is_default_seller' => true,
    ]);
    $second = app(CreateUser::class)->handle([
        'name' => 'Segundo', 'email' => 'b@example.com', 'password' => 'password123',
        'role' => 'seller', 'is_default_seller' => true,
    ]);

    expect($first->fresh()->is_default_seller)->toBeFalse()
        ->and($second->fresh()->is_default_seller)->toBeTrue();

    app(UpdateUser::class)->handle($first->fresh(), ['is_default_seller' => true]);

    expect($first->fresh()->is_default_seller)->toBeTrue()
        ->and($second->fresh()->is_default_seller)->toBeFalse()
        ->and(User::where('is_default_seller', true)->count())->toBe(1);
});

it('nadie se borra a sí mismo', function () {
    expect(fn () => app(DeleteUser::class)->handle($this->admin, $this->admin))
        ->toThrow(ValidationException::class);

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('el administrador puede borrar a otro usuario', function () {
    $other = User::factory()->seller()->create();

    app(DeleteUser::class)->handle($other, $this->admin);

    expect(User::find($other->id))->toBeNull();
});

it('el botón de borrar no se ofrece sobre uno mismo', function () {
    $other = User::factory()->seller()->create();

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $this->admin)
        ->assertTableActionVisible('delete', $other)
        ->callTableAction('delete', $other);

    expect(User::find($other->id))->toBeNull();
});

it('no permite asignar el rol superadmin desde las acciones', function () {
    expect(fn () => app(CreateUser::class)->handle([
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'role' => 'superadmin',
    ]))->toThrow(ValidationException::class);

    $seller = User::factory()->seller()->create();

    expect(fn () => app(UpdateUser::class)->handle($seller, ['role' => 'superadmin']))
        ->toThrow(ValidationException::class);
});

it('al editar, una contraseña vacía no se cambia', function () {
    $seller = User::factory()->seller()->create(['password' => 'password']);
    $hash = $seller->password;

    app(UpdateUser::class)->handle($seller, ['name' => 'Cambiado', 'password' => '']);

    expect($seller->fresh()->password)->toBe($hash)
        ->and(Hash::check('password', $seller->fresh()->password))->toBeTrue();
});

it('crea un usuario desde el formulario del panel', function () {
    Livewire::test(CreateUserPage::class)
        ->fillForm([
            'name' => 'Marta',
            'email' => 'marta@example.com',
            'password' => 'password123',
            'role' => 'seller',
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'marta@example.com')->firstOrFail();

    expect($user->role->value)->toBe('seller')
        ->and($user->active)->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

it('exige un email único y una contraseña al crear', function () {
    $existing = User::factory()->seller()->create();

    Livewire::test(CreateUserPage::class)
        ->fillForm(['name' => 'Dup', 'email' => $existing->email, 'role' => 'seller'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'password' => 'required']);
});
