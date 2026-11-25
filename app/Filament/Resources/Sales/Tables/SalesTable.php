<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (mixed $state, Sale $record): string => $record->number())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('id', (int) preg_replace('/\D/', '', $search)))
                    ->sortable(),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable(),
                TextColumn::make('seller.name')
                    ->label('Vendedor')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS', locale: 'es_AR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(SaleStatus::class),
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options(SaleChannel::class),
                SelectFilter::make('seller_id')
                    ->label('Vendedor')
                    // El SuperAdmin no aparece; el vendedor ya ve solo lo suyo.
                    ->options(fn (): array => User::query()->visibleToClient()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdmin()),
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
