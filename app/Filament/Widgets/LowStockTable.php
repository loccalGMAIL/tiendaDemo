<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\ProductVariant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Variantes que llegaron a su stock mínimo (módulo Stock, solo administrador).
 */
class LowStockTable extends AdministratorTableWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Productos con stock bajo';

    protected static function module(): Module
    {
        return Module::Stock;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ProductVariant::query()->with('product')->lowStock()->orderBy('stock')->limit(10))
            ->paginated(false)
            ->emptyStateHeading('Ningún producto llegó a su stock mínimo')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->formatStateUsing(fn (mixed $state, ProductVariant $record): string => $record->fullName()),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'warning'),
                TextColumn::make('min_stock')
                    ->label('Mínimo'),
            ]);
    }
}
