<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\Module;
use App\Filament\Concerns\RelationManagerBelongsToModule;
use App\Models\SaleItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Productos comprados por el cliente (módulo Cuenta corriente, parte de su ficha): los ítems
 * de sus ventas vigentes, sin anuladas. El vendedor no ve costos.
 */
class PurchasedItemsRelationManager extends RelationManager
{
    use RelationManagerBelongsToModule;

    protected static string $relationship = 'saleItems';

    protected static ?string $title = 'Productos comprados';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn ($query) => $query->with('sale'))
            ->columns([
                TextColumn::make('sale.date')
                    ->label('Fecha')
                    ->date('d/m/Y'),
                TextColumn::make('sale.id')
                    ->label('Venta')
                    ->formatStateUsing(fn (mixed $state, SaleItem $record): string => $record->sale->number()),
                TextColumn::make('description')
                    ->label('Producto')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Cantidad'),
                TextColumn::make('unit_price')
                    ->label('Precio')
                    ->money('ARS', locale: 'es_AR'),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('ARS', locale: 'es_AR'),
            ])
            ->defaultSort('sale_items.id', 'desc');
    }
}
