<?php

namespace Tests\Fixtures;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Models\User;
use Filament\Resources\Resource;

/**
 * Recurso de prueba que pertenece al módulo Stock.
 */
class StockResource extends Resource
{
    use BelongsToModule;

    protected static ?string $model = User::class;

    protected static function module(): Module
    {
        return Module::Stock;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getPages(): array
    {
        return [];
    }
}
