<?php

namespace App\Providers;

use App\Enums\Module;
use App\Services\Modules;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Modules::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En producción no se pueden correr migrate:fresh, db:wipe ni similares por accidente.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Fuera de producción, cargar una relación «a demanda» dentro de un listado (el clásico N+1)
        // lanza una excepción: se nota en desarrollo y en los tests. En producción no rompe la pantalla.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configureFilamentDefaults();
        $this->registerVersionHooks();
        $this->registerModuleDirective();
    }

    /**
     * Directiva Blade de módulo: el bloque entre @module('stock') y @endmodule solo se muestra con el módulo encendido.
     */
    private function registerModuleDirective(): void
    {
        Blade::if('module', function (string $key): bool {
            $module = Module::tryFrom($key);

            return $module !== null && app(Modules::class)->enabled($module);
        });
    }

    /**
     * Comportamiento común de todos los paneles.
     */
    private function configureFilamentDefaults(): void
    {
        // Los títulos de los recursos van como se escriben en español («Medios de pago»), no en Title Case.
        Resource::titleCaseModelLabel(false);

        // Todas las columnas de todas las tablas se pueden ocultar.
        Column::configureUsing(fn (Column $column) => $column->toggleable());

        // Botones con ícono: solo el ícono; el texto va como tooltip.
        $iconOnly = fn (Action $action) => $action
            ->iconButton()
            ->tooltip(fn (Action $action) => $action->getLabel());

        CreateAction::configureUsing(fn (CreateAction $action) => $iconOnly($action->icon(Heroicon::Plus)));
        EditAction::configureUsing(fn (EditAction $action) => $iconOnly($action->icon(Heroicon::PencilSquare)));
        ViewAction::configureUsing(fn (ViewAction $action) => $iconOnly($action->icon(Heroicon::Eye)));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $iconOnly($action->icon(Heroicon::Trash)));
    }

    /**
     * Versión del sistema visible en la barra superior y en el login.
     */
    private function registerVersionHooks(): void
    {
        $version = fn () => view('filament.version');

        FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_END, $version);
        FilamentView::registerRenderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, $version);
    }
}
