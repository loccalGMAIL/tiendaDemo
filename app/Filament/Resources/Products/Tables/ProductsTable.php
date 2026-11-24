<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\BarcodeLabels;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists('saleItems'))
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
                // Módulo Compras.
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => Modules::enabled(Module::Purchases)),
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
                // Módulo Catálogo web.
                IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean()
                    ->visible(fn (): bool => Modules::enabled(Module::Catalog)),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor habitual')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => Modules::enabled(Module::Purchases)),
                TernaryFilter::make('active')->label('Activo'),
                TernaryFilter::make('is_published')
                    ->label('Publicado en el catálogo')
                    ->visible(fn (): bool => Modules::enabled(Module::Catalog)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                // Módulo Códigos de barras: abre las etiquetas con una de cada variante de lo elegido.
                BulkActionGroup::make([
                    BulkAction::make('printLabels')
                        ->label('Imprimir etiquetas')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->visible(fn (): bool => BarcodeLabels::canAccess())
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => redirect()->to(
                            BarcodeLabels::urlFor(BarcodeLabels::selectionForProducts($records)),
                        )),
                ]),
            ]);
    }
}
