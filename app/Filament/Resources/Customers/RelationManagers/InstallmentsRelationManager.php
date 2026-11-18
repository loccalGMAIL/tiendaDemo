<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InstallmentStatus;
use App\Enums\Module;
use App\Filament\Concerns\RelationManagerBelongsToModule;
use App\Models\Installment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Vencimientos del cliente (módulo Cuenta corriente): lo que debe de cada venta a cuenta,
 * del más viejo al más nuevo. Solo lectura: se generan con la venta y cambian con los cobros.
 */
class InstallmentsRelationManager extends RelationManager
{
    use RelationManagerBelongsToModule;

    protected static string $relationship = 'installments';

    protected static ?string $title = 'Vencimientos';

    protected static ?string $modelLabel = 'vencimiento';

    protected static ?string $pluralModelLabel = 'vencimientos';

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
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with('sale'))
            ->columns([
                TextColumn::make('sale.id')
                    ->label('Venta')
                    ->formatStateUsing(fn (mixed $state, Installment $record): string => $record->sale->number()),
                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('ARS', locale: 'es_AR'),
                TextColumn::make('paid_amount')
                    ->label('Pagado')
                    ->money('ARS', locale: 'es_AR'),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->state(fn (Installment $record): string => $record->balance())
                    ->money('ARS', locale: 'es_AR'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('overdue')
                    ->label('Atraso')
                    ->state(fn (Installment $record): ?string => $record->isOverdue() ? $record->daysOverdue().' días' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(InstallmentStatus::class),
            ]);
    }
}
