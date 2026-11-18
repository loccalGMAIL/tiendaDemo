<?php

namespace App\Filament\Concerns;

use App\Enums\Module;
use App\Facades\Modules;
use Filament\Panel;
use Illuminate\Support\Arr;

/**
 * Para recursos y páginas de Filament que pertenecen a un módulo. Con el
 * módulo apagado desaparecen de la navegación, no se puede acceder y su
 * ruta responde 404. Las policies siguen aplicando encima.
 *
 * La clase declara su módulo con `protected static function module(): Module`
 * y no debe redefinir canAccess() ni shouldRegisterNavigation().
 *
 * Los widgets usan WidgetBelongsToModule (su método de acceso es otro).
 */
trait BelongsToModule
{
    abstract protected static function module(): Module;

    /**
     * Otros módulos que también tienen que estar encendidos (por ejemplo, un informe de
     * Compras, que además pide Informes). Se sobrescribe en la clase que lo necesita:
     * no hace falta redefinir canAccess().
     *
     * @return list<Module>
     */
    protected static function alsoRequires(): array
    {
        return [];
    }

    public static function canAccess(): bool
    {
        return static::requiredModulesEnabled() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::requiredModulesEnabled() && parent::shouldRegisterNavigation();
    }

    /**
     * @return string | array<int, string>
     */
    public static function getRouteMiddleware(Panel $panel): string|array
    {
        return [
            ...Arr::wrap(parent::getRouteMiddleware($panel)),
            ...array_map(
                fn (Module $module): string => 'module:'.$module->value,
                [static::module(), ...static::alsoRequires()],
            ),
        ];
    }

    protected static function requiredModulesEnabled(): bool
    {
        foreach ([static::module(), ...static::alsoRequires()] as $module) {
            if (! Modules::enabled($module)) {
                return false;
            }
        }

        return true;
    }
}
