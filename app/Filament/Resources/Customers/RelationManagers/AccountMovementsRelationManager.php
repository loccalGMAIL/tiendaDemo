<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\AccountMovementType;
use App\Enums\Module;
use App\Filament\Concerns\RelationManagerBelongsToModule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Cuenta corriente del cliente (módulo Cuenta corriente): historial inmutable de débitos y
 * créditos con el saldo acumulado.
 */
class AccountMovementsRelationManager extends RelationManager
{
    use RelationManagerBelongsToModule;

    protected static string $relationship = 'accountMovements';

    protected static ?string $title = 'Cuenta corriente';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'movimientos';

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Detalle')
                    ->wrap(),
                TextColumn::make('debit')
                    ->label('Debe')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                TextColumn::make('credit')
                    ->label('Haber')
                    ->money('ARS', locale: 'es_AR')
                    ->placeholder('—'),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('ARS', locale: 'es_AR')
                    ->weight('bold'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(AccountMovementType::class),
            ]);
    }
}
