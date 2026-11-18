<?php

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->admin()->create();
});

it('un administrador no puede crear un SuperAdmin aunque manipule el formulario', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->fillForm(['name' => 'Intruso', 'email' => 'intruso@cliente.test', 'password' => 'clave-segura-1', 'role' => Role::SuperAdmin->value])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'intruso@cliente.test')->exists())->toBeFalse();
});

it('un administrador no puede ascenderse a SuperAdmin ni ascender a otro', function () {
    $seller = User::factory()->seller()->create();

    foreach ([$this->admin, $seller] as $target) {
        Livewire::actingAs($this->admin)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['role' => Role::SuperAdmin->value])
            ->call('save')
            ->assertHasFormErrors(['role']);
    }

    expect($this->admin->fresh()->role)->toBe(Role::Admin)
        ->and($seller->fresh()->role)->toBe(Role::Seller);
});

it('un administrador no ve ni edita a un SuperAdmin (para él no existe)', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($this->admin)
        ->test(EditUser::class, ['record' => $superadmin->getKey()])
        ->assertNotFound();
});

it('un vendedor no entra a la gestión de usuarios', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get('http://'.config('domains.panel').'/users')
        ->assertForbidden();
});
