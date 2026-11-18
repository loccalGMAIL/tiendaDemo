<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\Customer;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/**
 * Cabecera de la ficha del cliente (módulo Cuenta corriente): lo que debe, lo vencido y
 * cuántos vencimientos tienen deuda.
 */
class CustomerAccountStats extends StatsOverviewWidget
{
    use WidgetBelongsToModule;

    protected static bool $isLazy = false;

    public ?Model $record = null;

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        if (! $this->record instanceof Customer) {
            return [];
        }

        $debt = $this->record->accountBalance();
        $overdue = $this->record->overdueBalance();
        $open = $this->record->installments()->open()->count();

        return [
            Stat::make('Saldo a cobrar', Money::format($debt))
                ->description($open > 0 ? "{$open} vencimientos con deuda" : 'Sin deuda')
                ->color(Money::compare($debt, '0') > 0 ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Vencido', Money::format($overdue))
                ->description(Money::compare($overdue, '0') > 0 ? 'Ya pasó la fecha de vencimiento' : 'Nada vencido')
                ->color(Money::compare($overdue, '0') > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }
}
