<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\Installment;
use App\Services\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ganancia futura y lo que vence el mes próximo (módulo Cuotas propias, solo administrador:
 * muestra ganancias).
 */
class InstallmentsOverview extends AdministratorStatsWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 8;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Cuotas';

    protected static function module(): Module
    {
        return Module::Installments;
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $open = Installment::query()->open()->get();

        $nextMonth = now()->startOfMonth()->addMonthNoOverflow();
        $dueNextMonth = $open->filter(fn (Installment $installment): bool => $installment->due_date->isSameMonth($nextMonth));

        return [
            Stat::make('Ganancia futura', Money::format(Money::sum($open->map(fn (Installment $installment): string => $installment->futureProfit())->all())))
                ->description('De todo lo que falta cobrar, con el interés de las cuotas')
                ->icon(Heroicon::OutlinedArrowTrendingUp),
            Stat::make('Vence el mes próximo', Money::format(Money::sum($dueNextMonth->map(fn (Installment $installment): string => $installment->balance())->all())))
                ->description($dueNextMonth->count().' '.($dueNextMonth->count() === 1 ? 'cuota' : 'cuotas'))
                ->icon(Heroicon::OutlinedCalendarDays),
        ];
    }
}
