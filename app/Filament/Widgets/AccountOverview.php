<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\Installment;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que hay por cobrar, lo vencido y cuántos clientes deben (módulo Cuenta corriente,
 * solo administrador).
 */
class AccountOverview extends AdministratorStatsWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Cuenta corriente';

    protected static function module(): Module
    {
        return Module::CurrentAccount;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $open = Installment::query()->open()->get();
        $overdue = $open->filter(fn (Installment $installment): bool => $installment->isOverdue());

        $total = Money::sum($open->map(fn (Installment $installment): string => $installment->balance())->all());
        $overdueTotal = Money::sum($overdue->map(fn (Installment $installment): string => $installment->balance())->all());
        $debtors = $open->pluck('customer_id')->unique()->count();

        return [
            Stat::make('Total por cobrar', Money::format($total))
                ->description($debtors.' '.($debtors === 1 ? 'cliente con deuda' : 'clientes con deuda'))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Vencido', Money::format($overdueTotal))
                ->description($overdue->count() > 0 ? $overdue->count().' vencimientos atrasados' : 'Nada vencido')
                ->color($overdue->count() > 0 ? 'danger' : 'success')
                ->icon(Heroicon::OutlinedExclamationTriangle),
        ];
    }
}
