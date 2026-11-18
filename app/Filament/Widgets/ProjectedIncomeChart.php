<?php

namespace App\Filament\Widgets;

use App\Enums\Module;
use App\Filament\Concerns\WidgetBelongsToModule;
use App\Models\Installment;
use App\Services\Money;

/**
 * Ingresos proyectados a 12 meses (módulo Cuotas propias, solo administrador): lo que falta
 * cobrar de las cuotas y su ganancia, por mes de vencimiento. Lo ya vencido cuenta en el primer mes.
 */
class ProjectedIncomeChart extends AdministratorChartWidget
{
    use WidgetBelongsToModule;

    protected static ?int $sort = 9;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Ingresos proyectados (próximos 12 meses)';

    protected ?string $description = 'Por mes de vencimiento. Lo vencido se suma al mes actual.';

    protected static function module(): Module
    {
        return Module::Installments;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $start = now()->startOfMonth();
        $labels = [];
        $amounts = array_fill(0, 12, '0.00');
        $profits = array_fill(0, 12, '0.00');

        foreach (range(0, 11) as $index) {
            $labels[] = ucfirst(str_replace('.', '', $start->copy()->addMonths($index)->locale('es')->translatedFormat('M Y')));
        }

        foreach (Installment::query()->open()->get() as $installment) {
            $index = $installment->due_date->lt($start)
                ? 0
                : (int) $start->diffInMonths($installment->due_date->copy()->startOfMonth());

            if ($index > 11) {
                continue;
            }

            $amounts[$index] = Money::add($amounts[$index], $installment->balance());
            $profits[$index] = Money::add($profits[$index], $installment->futureProfit());
        }

        return [
            'datasets' => [
                ['label' => 'Por cobrar', 'data' => array_map('floatval', $amounts)],
                ['label' => 'Ganancia', 'data' => array_map('floatval', $profits), 'backgroundColor' => '#16a34a'],
            ],
            'labels' => $labels,
        ];
    }
}
