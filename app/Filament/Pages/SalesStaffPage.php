<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Pages\Page;

/**
 * Base de las páginas que usa quien vende (administrador y vendedor). Las páginas de un módulo
 * la extienden y agregan su corte de módulo con BelongsToModule.
 */
abstract class SalesStaffPage extends Page
{
    public static function canAccess(): bool
    {
        return SaleResource::canCreate();
    }
}
