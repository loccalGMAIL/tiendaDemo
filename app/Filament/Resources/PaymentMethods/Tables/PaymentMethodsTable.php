<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('installments')
                    ->label('Cuotas')
                    ->sortable(),
                TextColumn::make('adjustment_percent')
                    ->label('Recargo / descuento')
                    ->suffix(' %')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->filters([
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
