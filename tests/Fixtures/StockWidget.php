<?php

namespace Tests\Fixtures;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use Filament\Widgets\Widget;

/**
 * Widget de prueba que pertenece al módulo Stock.
 */
class StockWidget extends Widget
{
    use WidgetBelongsToModule;

    protected string $view = 'filament.version';

    protected static function module(): Module
    {
        return Module::Stock;
    }
}
