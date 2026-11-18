<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (mixed $state, PurchaseOrder $record): string => $record->number())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('id', (int) preg_replace('/\D/', '', $search)))
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Productos')
                    ->counts('items'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(PurchaseOrderStatus::class),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
