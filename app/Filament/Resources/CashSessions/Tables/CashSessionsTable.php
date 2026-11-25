<?php

namespace App\Filament\Resources\CashSessions\Tables;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['register', 'openedBy', 'closedBy']))
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (mixed $state, CashSession $record): string => $record->number())
                    ->sortable(),
                TextColumn::make('register.name')
                    ->label('Caja'),
                TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (CashSession $record): string => $record->openedBy->name)
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (CashSession $record): ?string => $record->closedBy?->name)
                    ->placeholder('Abierto')
                    ->sortable(),
                TextColumn::make('expected_amount')
                    ->label('Esperado')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                TextColumn::make('counted_amount')
                    ->label('Contado')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                TextColumn::make('difference')
                    ->label('Diferencia')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match (true) {
                        $state === null => 'gray',
                        Money::compare($state, '0') === 0 => 'success',
                        default => 'danger',
                    })
                    ->weight('bold'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('cash_register_id')
                    ->label('Caja')
                    ->options(fn (): array => CashRegister::query()->orderBy('position')->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('open')
                    ->label('Estado')
                    ->trueLabel('Abiertos')
                    ->falseLabel('Cerrados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('closed_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('closed_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
