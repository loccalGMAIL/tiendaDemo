<?php

use App\Enums\ActivityEvent;
use App\Enums\Role;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
    $this->seller = User::factory()->seller()->create(['name' => 'Vera Vendedora']);
    $this->superadmin = User::factory()->superadmin()->create(['name' => 'Zeta Super']);

    $this->actingAs($this->admin);
});

it('no aparece en el listado de usuarios', function () {
    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin, $this->seller])
        ->assertCanNotSeeTableRecords([$this->superadmin])
        ->searchTable('Zeta')
        ->assertCanNotSeeTableRecords([$this->superadmin]);
});

it('su edición responde 404 en el panel admin', function () {
    $url = 'http://'.config('domains.panel').'/users/'.$this->superadmin->id.'/edit';

    $this->get($url)->assertNotFound();
    $this->get('http://'.config('domains.panel').'/users/'.$this->seller->id.'/edit')->assertOk();
});

it('no existe como opción de rol', function () {
    expect(array_keys(Role::assignable()))
        ->toBe(['admin', 'seller'])
        ->not->toContain('superadmin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password123',
            'role' => 'superadmin',
        ])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'intruso@example.com')->exists())->toBeFalse();
});

it('no aparece en el filtro de usuarios de la actividad', function () {
    $options = Livewire::test(ListActivityLogs::class)
        ->instance()
        ->getTable()
        ->getFilter('user_id')
        ->getOptions();

    expect($options)
        ->toHaveKey($this->admin->id)
        ->toHaveKey($this->seller->id)
        ->not->toHaveKey($this->superadmin->id);
});

it('sus ingresos no aparecen en la actividad del cliente', function () {
    event(new Login('web', $this->superadmin, false));
    event(new Login('web', $this->seller, false));

    $superLog = ActivityLog::where('user_id', $this->superadmin->id)->firstOrFail();
    $sellerLog = ActivityLog::where('user_id', $this->seller->id)->firstOrFail();

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$sellerLog])
        ->assertCanNotSeeTableRecords([$superLog]);
});

it('los intentos fallidos sobre el superadmin no aparecen en la actividad del cliente', function () {
    event(new Failed('web', $this->superadmin, ['email' => $this->superadmin->email, 'password' => 'x']));

    $log = ActivityLog::where('event', ActivityEvent::LoginFailed)->firstOrFail();

    Livewire::test(ListActivityLogs::class)->assertCanNotSeeTableRecords([$log]);
});

it('los cambios hechos sobre el superadmin no aparecen en la actividad del cliente', function () {
    $this->superadmin->update(['name' => 'Otro nombre']);

    $log = ActivityLog::where('subject_type', $this->superadmin->getMorphClass())
        ->where('subject_id', $this->superadmin->id)
        ->where('event', ActivityEvent::Updated)
        ->firstOrFail();

    // Lo hizo el admin logueado, pero el sujeto es el superadmin.
    expect($log->user_id)->toBe($this->admin->id);

    Livewire::test(ListActivityLogs::class)->assertCanNotSeeTableRecords([$log]);
});

it('lo que hace el superadmin sobre otros registros no aparece en la actividad del cliente', function () {
    $log = app(ActivityLogger::class)->log(
        ActivityEvent::Updated,
        'Cambio hecho por el superadmin',
        $this->seller,
        user: $this->superadmin,
    );

    Livewire::test(ListActivityLogs::class)->assertCanNotSeeTableRecords([$log]);
});

it('el panel admin no menciona el dominio del superadmin', function () {
    $this->get('http://'.config('domains.panel').'/')
        ->assertOk()
        ->assertDontSee(config('domains.superadmin'));

    $this->get('http://'.config('domains.panel').'/users')
        ->assertOk()
        ->assertDontSee(config('domains.superadmin'))
        ->assertDontSee('Zeta Super');
});
