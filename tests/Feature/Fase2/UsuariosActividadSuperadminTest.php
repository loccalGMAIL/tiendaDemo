<?php

use App\Enums\ActivityEvent;
use App\Enums\Role;
use App\Filament\Superadmin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Superadmin\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Superadmin\Resources\Users\Pages\CreateUser;
use App\Filament\Superadmin\Resources\Users\Pages\EditUser;
use App\Filament\Superadmin\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));

    $this->superadmin = User::factory()->superadmin()->create(['name' => 'Zeta Super']);
    $this->admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
    $this->seller = User::factory()->seller()->create(['name' => 'Vera Vendedora']);

    $this->url = 'http://'.config('domains.superadmin');
});

// --- Acceso -----------------------------------------------------------------

it('solo el SuperAdmin entra a Usuarios y a Actividad de la plataforma', function (string $path) {
    $this->actingAs($this->admin)->get($this->url.$path)->assertForbidden();
    $this->actingAs($this->seller)->get($this->url.$path)->assertForbidden();
    $this->actingAs($this->superadmin)->get($this->url.$path)->assertOk();
})->with(['usuarios' => ['/users'], 'actividad' => ['/activity-logs']]);

it('el menú de la plataforma ofrece Usuarios y Actividad', function () {
    $this->actingAs($this->superadmin)
        ->get($this->url.'/users')
        ->assertOk()
        ->assertSee('Usuarios')
        ->assertSee('Actividad')
        ->assertSee('Módulos')
        ->assertSee('Apariencia');
});

// --- Usuarios -----------------------------------------------------------------

it('lista a todos los usuarios, SuperAdmin incluido', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->superadmin, $this->admin, $this->seller]);
});

it('el panel del cliente sigue sin mostrar al SuperAdmin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    Livewire::test(App\Filament\Resources\Users\Pages\ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin, $this->seller])
        ->assertCanNotSeeTableRecords([$this->superadmin]);

    expect(array_keys(Role::assignable()))->toBe(['admin', 'seller']);
});

it('ofrece el rol SuperAdmin y crea usuarios de cualquier rol', function () {
    $this->actingAs($this->superadmin);

    expect(array_keys(Role::assignable(withSuperAdmin: true)))->toBe(['superadmin', 'admin', 'seller']);

    foreach (['superadmin', 'admin', 'seller'] as $role) {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => "Nuevo {$role}", 'email' => "{$role}@example.com", 'password' => 'password123', 'role' => $role])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', "{$role}@example.com")->firstOrFail();

        expect($created->role->value)->toBe($role)
            ->and(Hash::check('password123', $created->password))->toBeTrue();
    }
});

it('rechaza un rol que no existe', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'role' => 'dueño'])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(User::where('email', 'x@example.com')->exists())->toBeFalse();
});

it('edita a otro usuario: cambia el rol, lo desactiva y deja la contraseña si va vacía', function () {
    $this->actingAs($this->superadmin);
    $before = $this->seller->password;

    Livewire::test(EditUser::class, ['record' => $this->seller->getRouteKey()])
        ->fillForm(['name' => 'Vera Nueva', 'role' => 'superadmin', 'active' => false, 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $seller = $this->seller->fresh();

    expect($seller->name)->toBe('Vera Nueva')
        ->and($seller->role)->toBe(Role::SuperAdmin)
        ->and($seller->active)->toBeFalse()
        ->and($seller->password)->toBe($before);
});

it('no se puede cambiar el propio rol ni desactivarse', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(EditUser::class, ['record' => $this->superadmin->getRouteKey()])
        ->assertFormFieldIsDisabled('role')
        ->assertFormFieldIsDisabled('active')
        ->fillForm(['name' => 'Zeta Renombrado'])
        ->call('save')
        ->assertHasNoFormErrors();

    $self = $this->superadmin->fresh();

    expect($self->name)->toBe('Zeta Renombrado')
        ->and($self->role)->toBe(Role::SuperAdmin)
        ->and($self->active)->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->assertFormFieldIsEnabled('role')
        ->assertFormFieldIsEnabled('active');
});

it('borra a otros usuarios sin operaciones pero no a sí mismo', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $this->superadmin)
        ->assertTableActionVisible('delete', $this->seller)
        ->callTableAction('delete', $this->seller);

    expect(User::find($this->seller->id))->toBeNull()
        ->and(User::find($this->superadmin->id))->not->toBeNull();
});

it('filtra por rol, SuperAdmin incluido', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->filterTable('role', 'superadmin')
        ->assertCanSeeTableRecords([$this->superadmin])
        ->assertCanNotSeeTableRecords([$this->admin, $this->seller]);
});

// --- Actividad ------------------------------------------------------------------

it('muestra la actividad completa, también la del SuperAdmin y la hecha sobre él', function () {
    $this->actingAs($this->superadmin);

    event(new Login('web', $this->superadmin, false));
    event(new Login('web', $this->seller, false));
    $this->superadmin->update(['name' => 'Otro nombre']);

    $superLogin = ActivityLog::where('user_id', $this->superadmin->id)->where('event', ActivityEvent::Login)->firstOrFail();
    $sellerLogin = ActivityLog::where('user_id', $this->seller->id)->firstOrFail();
    $onSuper = ActivityLog::where('subject_type', $this->superadmin->getMorphClass())
        ->where('subject_id', $this->superadmin->id)
        ->where('event', ActivityEvent::Updated)
        ->firstOrFail();

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$superLogin, $sellerLogin, $onSuper]);
});

it('el filtro de usuario de la actividad incluye al SuperAdmin', function () {
    $this->actingAs($this->superadmin);

    $options = Livewire::test(ListActivityLogs::class)
        ->instance()
        ->getTable()
        ->getFilter('user_id')
        ->getOptions();

    expect($options)->toHaveKey($this->superadmin->id)->toHaveKey($this->admin->id)->toHaveKey($this->seller->id);
});

it('abre el detalle de un registro y es de solo lectura', function () {
    $this->actingAs($this->superadmin);

    $log = app(ActivityLogger::class)->log(ActivityEvent::Updated, 'Cambio de prueba', $this->seller, user: $this->superadmin);

    Livewire::test(ViewActivityLog::class, ['record' => $log->getRouteKey()])->assertSee('Cambio de prueba');

    $this->get($this->url.'/activity-logs/create')->assertNotFound();
});

it('el panel del cliente sigue sin mostrar la actividad del SuperAdmin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);

    event(new Login('web', $this->superadmin, false));
    $log = ActivityLog::where('user_id', $this->superadmin->id)->firstOrFail();

    Livewire::test(App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs::class)->assertCanNotSeeTableRecords([$log]);
});

// --- Textos ---------------------------------------------------------------------

it('el acceso dice «Ingrese a su cuenta» en los dos paneles', function (string $domain) {
    $this->get('http://'.config($domain).'/login')
        ->assertOk()
        ->assertSee('Ingrese a su cuenta')
        ->assertDontSee('Entre a su cuenta');
})->with(['cliente' => ['domains.panel'], 'plataforma' => ['domains.superadmin']]);

it('el inicio se llama «Dashboard» en los dos paneles', function () {
    $this->actingAs($this->superadmin)->get($this->url.'/')->assertOk()->assertSee('Dashboard')->assertDontSee('Escritorio');

    $this->actingAs($this->admin)->get('http://'.config('domains.panel').'/')->assertOk()->assertSee('Dashboard')->assertDontSee('Escritorio');
});
