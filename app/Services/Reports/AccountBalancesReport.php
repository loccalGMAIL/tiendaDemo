<?php

namespace App\Services\Reports;

use App\Models\Installment;
use App\Services\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Saldos pendientes por cliente (módulo Cuenta corriente): una foto de hoy. Una fila por
 * cliente con deuda, con lo vencido, lo que vence cada mes (el actual y los siguientes, hasta
 * 6, 12, 18 o 24), lo que vence más adelante y el total.
 */
class AccountBalancesReport extends Report
{
    public const MONTHS = [6, 12, 18, 24];

    /**
     * @param  array<string, mixed>  $filters  months (6, 12, 18 o 24)
     * @return array{
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>,
     *     months: int
     * }
     */
    public function run(array $filters): array
    {
        $months = $this->months($filters);
        $today = now()->startOfDay();

        $installments = Installment::query()->open()->with('customer')->orderBy('id')->get();

        $keys = ['overdue', ...array_map(fn (int $i): string => "m{$i}", range(0, $months - 1)), 'later'];

        $rows = $installments
            ->groupBy('customer_id')
            ->map(function (Collection $group) use ($keys, $months, $today): array {
                $row = ['label' => $group->first()?->customer->name ?? ''] + array_fill_keys($keys, '0.00');

                foreach ($group as $installment) {
                    /** @var Installment $installment */
                    $key = $this->bucket($installment, $months, $today);
                    $row[$key] = Money::add($row[$key], $installment->balance());
                }

                $row['total'] = Money::sum(array_map(fn (string $key): string => $row[$key], $keys));

                return $row;
            })
            ->sortBy('label')
            ->values()
            ->all();

        $totals = ['label' => 'Total ('.count($rows).' clientes)'];

        foreach ([...$keys, 'total'] as $key) {
            $totals[$key] = Money::sum(array_column($rows, $key));
        }

        return [
            'columns' => $this->columns($months, $today),
            'rows' => $rows,
            'totals' => $totals,
            'months' => $months,
        ];
    }

    public function describe(array $filters): string
    {
        return 'Al '.now()->format('d/m/Y').' · próximos '.$this->months($filters).' meses';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function months(array $filters): int
    {
        $months = (int) ($filters['months'] ?? 6);

        return in_array($months, self::MONTHS, true) ? $months : 6;
    }

    /**
     * Vencido (la fecha ya pasó), el mes de vencimiento (0 = el actual) o más adelante.
     */
    private function bucket(Installment $installment, int $months, Carbon $today): string
    {
        if ($installment->due_date->lt($today)) {
            return 'overdue';
        }

        $index = (int) $today->copy()->startOfMonth()->diffInMonths($installment->due_date->copy()->startOfMonth());

        return $index < $months ? "m{$index}" : 'later';
    }

    /**
     * @return list<array{key: string, label: string, kind: string}>
     */
    private function columns(int $months, Carbon $today): array
    {
        $columns = [
            ['key' => 'label', 'label' => 'Cliente', 'kind' => 'text'],
            ['key' => 'overdue', 'label' => 'Vencido', 'kind' => 'money'],
        ];

        foreach (range(0, $months - 1) as $index) {
            $columns[] = [
                'key' => "m{$index}",
                // «nov. 2026» → «Nov 2026»: sin el punto de la abreviatura.
                'label' => ucfirst(str_replace('.', '', $today->copy()->startOfMonth()->addMonths($index)->locale('es')->translatedFormat('M Y'))),
                'kind' => 'money',
            ];
        }

        $columns[] = ['key' => 'later', 'label' => 'Más adelante', 'kind' => 'money'];
        $columns[] = ['key' => 'total', 'label' => 'Total', 'kind' => 'money'];

        return $columns;
    }
}
