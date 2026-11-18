<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Installment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Vencimientos atrasados, los más viejos primero, con acceso directo a cobrar (módulo
 * Cuenta corriente, solo administrador).
 */
class OverdueInstallments extends AdministratorTableWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 7;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Vencidos';

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Installment::query()->overdue()->oldestFirst()->with(['customer', 'sale'])->limit(5))
            ->paginated(false)
            ->emptyStateHeading('No hay vencimientos atrasados')
            ->columns([
                TextColumn::make('customer.name')->label('Cliente'),
                TextColumn::make('sale.id')
                    ->label('Venta')
                    ->formatStateUsing(fn (mixed $state, Installment $record): string => $record->sale->number()),
                TextColumn::make('due_date')->label('Vencimiento')->date('d/m/Y'),
                TextColumn::make('days')
                    ->label('Atraso')
                    ->state(fn (Installment $record): string => $record->daysOverdue().' días')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->state(fn (Installment $record): string => $record->balance())
                    ->money('ARS', locale: 'es_AR'),
            ])
            ->recordActions([
                Action::make('collect')
                    ->label('Cobrar')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->url(fn (Installment $record): string => PaymentResource::getUrl('create', ['customer' => $record->customer_id])),
            ]);
    }
}
