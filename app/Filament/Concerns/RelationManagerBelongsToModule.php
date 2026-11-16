<?php

namespace App\Filament\Concerns;

use App\Enums\Module;
use App\Facades\Modules;
use Illuminate\Database\Eloquent\Model;

/**
 * Para relation managers de Filament que pertenecen a un módulo: con el módulo
 * apagado no se muestran. La clase declara su módulo con
 * `protected static function module(): Module` y no debe redefinir canViewForRecord().
 */
trait RelationManagerBelongsToModule
{
    abstract protected static function module(): Module;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Modules::enabled(static::module()) && parent::canViewForRecord($ownerRecord, $pageClass);
    }
}
