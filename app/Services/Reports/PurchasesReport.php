<?php

namespace App\Services\Reports;

use App\Models\Purchase;
use App\Services\Money;
use Illuminate\Support\Collection;

/**
 * Compras por proveedor: facturas de compra vigentes de un período (sin anuladas),
 * agrupadas por proveedor o en detalle, una fila por factura.
 */
class PurchasesReport extends Report
{
    public const GROUPS = ['supplier' => 'Por proveedor', 'detail' => 'Detalle'];

    /**
     * @param  array<string, mixed>  $filters  from, to, supplier_id, group
     * @return array{
     *     group: string,
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>
     * }
     */
    public function run(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $supplierId = $this->id($filters['supplier_id'] ?? null);
        $group = array_key_exists((string) ($filters['group'] ?? ''), self::GROUPS) ? (string) $filters['group'] : 'supplier';

        $purchases = Purchase::query()
            ->with(['supplier', 'order'])
            ->notCancelled()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($supplierId, fn ($query, int $id) => $query->where('supplier_id', $id))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $group === 'detail' ? $this->detail($purchases) : $this->bySupplier($purchases);
    }

    /**
     * @param  Collection<int, Purchase>  $purchases
     * @return array{
     *     group: string,
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>
     * }
     */
    private function bySupplier(Collection $purchases): array
    {
        $rows = $purchases
            ->groupBy('supplier_id')
            ->map(function (Collection $group): array {
                $total = $this->total($group);

                return [
                    'label' => $group->first()?->supplier->name ?? '',
                    'count' => (string) $group->count(),
                    'total' => $total,
                    'average' => Money::round(bcdiv($total, (string) $group->count(), 8)),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();

        $total = $this->total($purchases);

        return [
            'group' => 'supplier',
            'columns' => [
                ['key' => 'label', 'label' => 'Proveedor', 'kind' => 'text'],
                ['key' => 'count', 'label' => 'Facturas', 'kind' => 'number'],
                ['key' => 'total', 'label' => 'Total comprado', 'kind' => 'money'],
                ['key' => 'average', 'label' => 'Factura promedio', 'kind' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'label' => 'Total ('.count($rows).' proveedores)',
                'count' => (string) $purchases->count(),
                'total' => $total,
                'average' => $purchases->isEmpty() ? '0.00' : Money::round(bcdiv($total, (string) $purchases->count(), 8)),
            ],
        ];
    }

    /**
     * @param  Collection<int, Purchase>  $purchases
     * @return array{
     *     group: string,
     *     columns: list<array{key: string, label: string, kind: string}>,
     *     rows: list<array<string, string>>,
     *     totals: array<string, string>
     * }
     */
    private function detail(Collection $purchases): array
    {
        $rows = $purchases->map(fn (Purchase $purchase): array => [
            'label' => $purchase->invoice_number,
            'date' => $purchase->date->format('d/m/Y'),
            'supplier' => $purchase->supplier->name,
            'order' => $purchase->order?->number() ?? '—',
            'total' => (string) $purchase->total,
        ])->values()->all();

        return [
            'group' => 'detail',
            'columns' => [
                ['key' => 'label', 'label' => 'Factura', 'kind' => 'text'],
                ['key' => 'date', 'label' => 'Fecha', 'kind' => 'text'],
                ['key' => 'supplier', 'label' => 'Proveedor', 'kind' => 'text'],
                ['key' => 'order', 'label' => 'Orden', 'kind' => 'text'],
                ['key' => 'total', 'label' => 'Total', 'kind' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                'label' => 'Total ('.count($rows).' facturas)',
                'total' => $this->total($purchases),
            ],
        ];
    }

    /**
     * @param  Collection<int, Purchase>  $purchases
     */
    private function total(Collection $purchases): string
    {
        return Money::sum($purchases->map(fn (Purchase $purchase): string => (string) $purchase->total)->all());
    }
}
