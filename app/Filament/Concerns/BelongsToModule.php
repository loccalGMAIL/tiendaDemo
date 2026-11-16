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

    public static function canAccess(): bool
    {
        return Modules::enabled(static::module()) && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Modules::enabled(static::module()) && parent::shouldRegisterNavigation();
    }

    /**
     * @return string | array<int, string>
     */
    public static function getRouteMiddleware(Panel $panel): string|array
    {
        return [
            ...Arr::wrap(parent::getRouteMiddleware($panel)),
            'module:'.static::module()->value,
        ];
    }
}
