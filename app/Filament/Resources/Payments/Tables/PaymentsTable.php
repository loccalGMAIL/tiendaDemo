<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Customer;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (mixed $state, Payment $record): string => $record->number())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('id', (int) preg_replace('/\D/', '', $search)))
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('method.name')
                    ->label('Medio de pago'),
                TextColumn::make('base_amount')
                    ->label('Baja la deuda')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Cobrado')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('collector.name')
                    ->label('Cobrador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (Payment $record): string => $record->isCancelled() ? 'Anulado' : 'Vigente')
                    ->color(fn (Payment $record): string => $record->isCancelled() ? 'danger' : 'success'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Estado')
                    ->options(['active' => 'Vigente', 'cancelled' => 'Anulado'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->whereNull('cancelled_at'),
                        'cancelled' => $query->whereNotNull('cancelled_at'),
                        default => $query,
                    }),
                SelectFilter::make('customer_id')
                    ->label('Cliente')
                    ->searchable()
                    ->options(fn (): array => Customer::query()->orderBy('name')->limit(200)->pluck('name', 'id')->all()),
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
