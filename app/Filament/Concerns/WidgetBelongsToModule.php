<?php

namespace App\Filament\Concerns;

use App\Enums\Module;
use App\Facades\Modules;

/**
 * Para widgets de Filament que pertenecen a un módulo: con el módulo apagado
 * no se muestran. La clase declara su módulo con
 * `protected static function module(): Module` y no debe redefinir canView().
 */
trait WidgetBelongsToModule
{
    abstract protected static function module(): Module;

    public static function canView(): bool
    {
        return Modules::enabled(static::module()) && parent::canView();
    }
}
