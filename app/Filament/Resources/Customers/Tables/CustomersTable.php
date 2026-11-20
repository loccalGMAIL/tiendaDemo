<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists('sales'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('active')->label('Activo'),
            ])
            ->recordActions([
                // La ficha del cliente: la ven también los vendedores, que no editan.
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
