<?php

namespace Tests\Fixtures;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use Filament\Pages\Page;

/**
 * Página de prueba que pertenece al módulo Stock.
 */
class StockPage extends Page
{
    use BelongsToModule;

    protected static function module(): Module
    {
        return Module::Stock;
    }
}
