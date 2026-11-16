<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['variant.product', 'user', 'reference']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('variant.product.name')
                    ->label('Producto')
                    ->searchable(),
                TextColumn::make('variant.name')
                    ->label('Variante')
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('reference')
                    ->label('Origen')
                    ->state(fn (StockMovement $record): ?string => $record->reference instanceof Sale ? 'Venta '.$record->reference->number() : null)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(StockMovementType::class),
                SelectFilter::make('product')
                    ->label('Producto')
                    ->searchable()
                    ->options(fn (): array => Product::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, mixed $productId) => $q->whereHas('variant', fn (Builder $variant) => $variant->where('product_id', $productId)),
                    )),
                Filter::make('period')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ]);
    }
}
