<?php

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('registra el ingreso', function () {
    $admin = User::factory()->admin()->create();
    ActivityLog::query()->delete();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate');

    $log = ActivityLog::where('event', ActivityEvent::Login)->firstOrFail();

    expect($log->user_id)->toBe($admin->id)
        ->and($log->ip_address)->not->toBeNull();
});

it('registra la salida', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    auth()->logout();

    $log = ActivityLog::where('event', ActivityEvent::Logout)->firstOrFail();
    expect($log->user_id)->toBe($admin->id);
});

it('registra el intento fallido con el email y sin la contraseña', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'nadie@example.com', 'password' => 'secreta-123'])
        ->call('authenticate');

    $log = ActivityLog::where('event', ActivityEvent::LoginFailed)->firstOrFail();

    expect($log->properties)->toBe(['email' => 'nadie@example.com'])
        ->and($log->user_id)->toBeNull()
        ->and(json_encode($log->toArray()))->not->toContain('secreta-123');
});

it('registra altas, cambios y bajas de usuarios con antes y después', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $user = app(CreateUser::class)->handle([
        'name' => 'Nuevo Vendedor',
        'email' => 'nuevo@example.com',
        'password' => 'password123',
        'role' => 'seller',
    ]);

    $created = ActivityLog::where('event', ActivityEvent::Created)->where('subject_id', $user->id)->firstOrFail();
    expect($created->user_id)->toBe($admin->id)
        ->and($created->properties['new'])->toMatchArray(['name' => 'Nuevo Vendedor', 'email' => 'nuevo@example.com', 'role' => 'seller']);

    app(UpdateUser::class)->handle($user, ['name' => 'Vendedor Renombrado', 'role' => 'admin']);

    $updated = ActivityLog::where('event', ActivityEvent::Updated)->where('subject_id', $user->id)->firstOrFail();
    expect($updated->properties['old'])->toBe(['name' => 'Nuevo Vendedor', 'role' => 'seller'])
        ->and($updated->properties['new'])->toBe(['name' => 'Vendedor Renombrado', 'role' => 'admin']);

    app(DeleteUser::class)->handle($user, $admin);

    $deleted = ActivityLog::where('event', ActivityEvent::Deleted)->where('subject_id', $user->id)->firstOrFail();
    expect($deleted->properties['old'])->toMatchArray(['email' => 'nuevo@example.com']);
});

it('nunca guarda contraseñas ni hashes', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $user = app(CreateUser::class)->handle([
        'name' => 'Con Clave',
        'email' => 'clave@example.com',
        'password' => 'password123',
        'role' => 'seller',
    ]);
    app(UpdateUser::class)->handle($user, ['password' => 'otra-clave-456']);

    $update = ActivityLog::where('event', ActivityEvent::Updated)->where('subject_id', $user->id)->firstOrFail();
    expect($update->properties['sensitive_changed'])->toBe(['password']);

    $dump = json_encode(ActivityLog::all()->toArray());
    expect($dump)->not->toContain('$2y$')
        ->not->toContain('password123')
        ->not->toContain('otra-clave-456')
        ->not->toContain('remember_token');
});

it('el registro es inmutable', function () {
    $log = ActivityLog::create(['event' => ActivityEvent::Login, 'description' => 'x']);

    expect(Schema::hasColumn('activity_logs', 'updated_at'))->toBeFalse();
    expect(fn () => $log->update(['description' => 'cambiado']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(ActivityLog::find($log->id)->description)->toBe('x');
});
