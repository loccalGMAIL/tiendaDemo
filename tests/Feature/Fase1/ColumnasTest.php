<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\TableColumnPreference;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function emailIsToggled(mixed $component): bool
{
    return collect($component->get('tableColumns'))->firstWhere('name', 'email')['isToggled'];
}

function hideEmailColumn(mixed $component): void
{
    $state = collect($component->get('tableColumns'))
        ->map(fn (array $column) => $column['name'] === 'email' ? [...$column, 'isToggled' => false] : $column)
        ->all();

    $component->call('applyTableColumnManager', $state);
}

it('todas las columnas se pueden ocultar', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $columns = collect(Livewire::test(ListUsers::class)->get('tableColumns'));

    expect($columns)->not->toBeEmpty()
        ->and($columns->every(fn (array $column) => $column['isToggleable']))->toBeTrue();
});

it('guarda en la base las columnas ocultas por usuario y tabla', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    hideEmailColumn($component = Livewire::test(ListUsers::class));

    $preference = TableColumnPreference::where('user_id', $admin->id)->where('table', ListUsers::class)->first();

    expect($preference)->not->toBeNull()
        ->and(emailIsToggled($component))->toBeFalse();
});

it('recuerda la elección al volver a abrir la tabla', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    hideEmailColumn(Livewire::test(ListUsers::class));

    expect(emailIsToggled(Livewire::test(ListUsers::class)))->toBeFalse();
});

it('la elección de un usuario no afecta a otro', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin);
    hideEmailColumn(Livewire::test(ListUsers::class));

    $this->actingAs($otherAdmin);
    expect(emailIsToggled(Livewire::test(ListUsers::class)))->toBeTrue();
    expect(TableColumnPreference::where('user_id', $otherAdmin->id)->exists())->toBeFalse();
});

it('no escribe nada en la base mientras la tabla esté como viene por defecto', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListUsers::class);

    expect(TableColumnPreference::count())->toBe(0);
});

it('mantiene una sola fila por usuario y tabla', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $component = Livewire::test(ListUsers::class);
    hideEmailColumn($component);
    $component->call('resetTableColumnManager');

    expect(TableColumnPreference::where('user_id', $admin->id)->count())->toBe(1)
        ->and(emailIsToggled($component))->toBeTrue();
});
