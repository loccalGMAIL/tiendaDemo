<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Historial de movimientos de stock (módulo Stock): solo lectura.
 */
class StockMovementResource extends Resource
{
    use BelongsToModule;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static ?string $modelLabel = 'movimiento de stock';

    protected static ?string $pluralModelLabel = 'movimientos de stock';

    protected static ?string $navigationLabel = 'Movimientos de stock';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 5;

    protected static function module(): Module
    {
        return Module::Stock;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
