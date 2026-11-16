<?php

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

it('crear, editar, ver y borrar son botones de solo ícono con el texto como tooltip', function (string $class) {
    /** @var Action $action */
    $action = $class::make();

    expect($action->isIconButton())->toBeTrue()
        ->and($action->getIcon())->not->toBeNull()
        ->and($action->getTooltip())->toBe($action->getLabel())
        ->and($action->getLabel())->not->toBeEmpty();
})->with([CreateAction::class, EditAction::class, ViewAction::class, DeleteAction::class]);

it('las acciones sin ícono conservan su texto', function () {
    $action = Action::make('guardar')->label('Guardar');

    expect($action->isIconButton())->toBeFalse();
});

it('muestra la versión en el login de ambos paneles', function (string $domain) {
    $this->withoutVite();

    $this->get('http://'.config($domain).'/login')
        ->assertOk()
        ->assertSee('v'.config('platform.version'));
})->with(['domains.panel', 'domains.superadmin']);

it('muestra la versión en la barra superior', function (string $role, string $domain) {
    $this->withoutVite();
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->get('http://'.config($domain).'/')
        ->assertOk()
        ->assertSee('v'.config('platform.version'));
})->with([
    'panel admin' => ['admin', 'domains.panel'],
    'panel superadmin' => ['superadmin', 'domains.superadmin'],
]);

it('la versión de config coincide con el CHANGELOG', function () {
    expect(file_get_contents(base_path('CHANGELOG.md')))
        ->toContain('## '.config('platform.version'));
});
