<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Models\Purchase;
use App\Models\Supplier;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Factura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('order.id')
                    ->label('Orden')
                    ->formatStateUsing(fn (mixed $state, Purchase $record): string => $record->order?->number() ?? '—')
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('state')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Purchase $record): string => $record->isCancelled() ? 'Anulada' : 'Vigente')
                    ->color(fn (Purchase $record): string => $record->isCancelled() ? 'danger' : 'success'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Estado')
                    ->options(['active' => 'Vigente', 'cancelled' => 'Anulada'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->whereNull('cancelled_at'),
                        'cancelled' => $query->whereNotNull('cancelled_at'),
                        default => $query,
                    }),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('period')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
