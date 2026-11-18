<?php

namespace App\Services\Reports;

use App\Models\Installment;
use App\Services\Money;
use Illuminate\Support\Carbon;

/**
 * Cobranza del mes (módulo Cuenta corriente): los vencimientos con deuda que vencen en el
 * mes elegido, con los datos para salir a cobrar (teléfono, dirección), los días de atraso
 * y el saldo. Opcionalmente suma los atrasados de meses anteriores.
 */
class CollectionsReport extends Report
{
    /**
     * @param  array<string, mixed>  $filters  month (AAAA-MM; por defecto el actual), include_overdue
     * @return array{
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>
     * }
     */
    public function run(array $filters): array
    {
        $month = $this->month($filters);
        $includeOverdue = $this->includeOverdue($filters);
        $today = now()->startOfDay();

        $installments = Installment::query()
            ->open()
            ->with(['customer', 'sale'])
            ->where(function ($query) use ($month, $includeOverdue): void {
                $query->whereBetween('due_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);

                if ($includeOverdue) {
                    $query->orWhereDate('due_date', '<', $month->toDateString());
                }
            })
            ->oldestFirst()
            ->get();

        $rows = $installments->map(fn (Installment $installment): array => [
            'label' => $installment->customer->name,
            'phone' => $installment->customer->phone ?? '—',
            'address' => $this->address($installment),
            'sale' => $installment->sale->number(),
            'due' => $installment->due_date->format('d/m/Y'),
            'days' => (string) ($installment->due_date->lt($today) ? (int) $installment->due_date->diffInDays($today) : 0),
            'balance' => $installment->balance(),
        ])->values()->all();

        return [
            'columns' => [
                ['key' => 'label', 'label' => 'Cliente', 'kind' => 'text'],
                ['key' => 'phone', 'label' => 'Teléfono', 'kind' => 'text'],
                ['key' => 'address', 'label' => 'Dirección', 'kind' => 'text'],
                ['key' => 'sale', 'label' => 'Venta', 'kind' => 'text'],
                ['key' => 'due', 'label' => 'Vence', 'kind' => 'text'],
                ['key' => 'days', 'label' => 'Días de atraso', 'kind' => 'number'],
                ['key' => 'balance', 'label' => 'Saldo', 'kind' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'label' => 'Total ('.count($rows).' vencimientos)',
                'balance' => Money::sum(array_column($rows, 'balance')),
            ],
        ];
    }

    public function describe(array $filters): string
    {
        return 'Vencimientos de '.$this->month($filters)->locale('es')->translatedFormat('F \d\e Y')
            .($this->includeOverdue($filters) ? ' y atrasados' : '');
    }

    /**
     * Primer día del mes pedido (AAAA-MM); sin un mes válido, el actual.
     *
     * @param  array<string, mixed>  $filters
     */
    public function month(array $filters): Carbon
    {
        $value = $filters['month'] ?? null;

        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            return Carbon::createFromFormat('!Y-m', $value) ?: now()->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function includeOverdue(array $filters): bool
    {
        return filter_var($filters['include_overdue'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function address(Installment $installment): string
    {
        return collect([$installment->customer->address, $installment->customer->city])->filter()->implode(', ') ?: '—';
    }
}
