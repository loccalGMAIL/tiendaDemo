<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Marca')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                // El vendedor no ve costos.
                TextColumn::make('cost')
                    ->label('Costo')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdmin()),
                // Existencia total del producto (suma de sus variantes): módulo Stock.
                TextColumn::make('variants_sum_stock')
                    ->label('Stock')
                    ->sum('variants', 'stock')
                    ->sortable()
                    ->color(fn (mixed $state): ?string => (int) $state < 0 ? 'danger' : null)
                    ->visible(fn (): bool => Modules::enabled(Module::Stock)),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
