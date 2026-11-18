<?php

namespace App\Services\Reports;

use App\Models\Payment;
use App\Services\Money;
use Illuminate\Support\Collection;

/**
 * Cobros realizados de un período, sin anulados, con subtotales por medio de pago
 * y por cobrador.
 */
class PaymentsReport extends Report
{
    /**
     * @param  array<string, mixed>  $filters  from, to, payment_method_id, collected_by
     * @return array{
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>,
     *     subtotals: array<string, list<array<string, string>>>
     * }
     */
    public function run(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $methodId = $this->id($filters['payment_method_id'] ?? null);
        $collectorId = $this->id($filters['collected_by'] ?? null);

        $payments = Payment::query()
            ->with(['sale', 'customer', 'method', 'collector'])
            ->notCancelled()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($methodId, fn ($query, int $id) => $query->where('payment_method_id', $id))
            ->when($collectorId, fn ($query, int $id) => $query->where('collected_by', $id))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $rows = $payments->map(fn (Payment $payment): array => [
            'label' => $payment->number(),
            'date' => $payment->date->format('d/m/Y'),
            'sale' => $payment->sale?->number() ?? '—',
            'customer' => $payment->customer_id === null ? '—' : $payment->customer->name,
            'method' => $payment->method->name,
            'collector' => $payment->collector->name,
            'base' => (string) $payment->base_amount,
            'adjustment' => (string) $payment->adjustment_amount,
            'amount' => (string) $payment->amount,
        ])->values()->all();

        return [
            'columns' => [
                ['key' => 'label', 'label' => 'Cobro', 'kind' => 'text'],
                ['key' => 'date', 'label' => 'Fecha', 'kind' => 'text'],
                ['key' => 'sale', 'label' => 'Venta', 'kind' => 'text'],
                ['key' => 'customer', 'label' => 'Cliente', 'kind' => 'text'],
                ['key' => 'method', 'label' => 'Medio de pago', 'kind' => 'text'],
                ['key' => 'collector', 'label' => 'Cobrador', 'kind' => 'text'],
                ['key' => 'base', 'label' => 'Base', 'kind' => 'money'],
                ['key' => 'adjustment', 'label' => 'Ajuste', 'kind' => 'money'],
                ['key' => 'amount', 'label' => 'Monto', 'kind' => 'money'],
            ],
            'rows' => $rows,
            'totals' => ['label' => 'Total ('.count($rows).' cobros)', ...$this->sums($payments)],
            'subtotals' => [
                'Por medio de pago' => $this->grouped($payments, fn (Payment $payment): string => $payment->method->name),
                'Por cobrador' => $this->grouped($payments, fn (Payment $payment): string => $payment->collector->name),
            ],
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  callable(Payment): string  $by
     * @return list<array<string, string>>
     */
    private function grouped(Collection $payments, callable $by): array
    {
        return $payments
            ->groupBy($by)
            ->map(fn (Collection $group, string $label): array => ['label' => $label, 'count' => (string) $group->count(), ...$this->sums($group)])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, string>
     */
    private function sums(Collection $payments): array
    {
        return [
            'base' => Money::sum($payments->map(fn (Payment $payment): string => (string) $payment->base_amount)->all()),
            'adjustment' => Money::sum($payments->map(fn (Payment $payment): string => (string) $payment->adjustment_amount)->all()),
            'amount' => Money::sum($payments->map(fn (Payment $payment): string => (string) $payment->amount)->all()),
        ];
    }
}
