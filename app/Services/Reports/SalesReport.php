<?php

namespace App\Services\Reports;

use App\Enums\SaleChannel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Money;
use Illuminate\Support\Collection;

/**
 * Ventas y ganancia de un período, sin anuladas. La ganancia es el subtotal de los
 * ítems menos su costo: el recargo o descuento de los medios de pago se informa
 * aparte (columna Ajustes) y no entra en la ganancia.
 *
 * @phpstan-type Columns list<array{key: string, label: string, kind: string}>
 * @phpstan-type Result array{group: string, columns: Columns, rows: list<array<string, string>>, totals: array<string, string>}
 */
class SalesReport extends Report
{
    public const GROUPS = ['detail' => 'Detalle', 'seller' => 'Por vendedor', 'channel' => 'Por canal', 'product' => 'Por producto'];

    /**
     * @param  array<string, mixed>  $filters  from, to, seller_id, channel, group
     * @return Result
     */
    public function run(array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $sellerId = $this->id($filters['seller_id'] ?? null);
        $channel = SaleChannel::tryFrom((string) ($filters['channel'] ?? ''));
        $group = array_key_exists((string) ($filters['group'] ?? ''), self::GROUPS) ? (string) $filters['group'] : 'detail';

        $sales = Sale::query()
            ->with(['customer', 'seller'])
            ->notCancelled()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($sellerId, fn ($query, int $id) => $query->where('seller_id', $id))
            ->when($channel, fn ($query, SaleChannel $channel) => $query->where('channel', $channel->value))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return match ($group) {
            'seller' => $this->bySeller($sales),
            'channel' => $this->byChannel($sales),
            'product' => $this->byProduct($sales),
            default => $this->detail($sales),
        };
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return Result
     */
    private function detail(Collection $sales): array
    {
        $rows = $sales->map(fn (Sale $sale): array => [
            'label' => $sale->number(),
            'date' => $sale->date->format('d/m/Y'),
            'customer' => $sale->customer_id === null ? 'Consumidor final' : $sale->customer->name,
            'seller' => $sale->seller->name,
            ...$this->amounts((string) $sale->subtotal, (string) $sale->adjustment, (string) $sale->total, (string) $sale->total_cost),
        ])->values()->all();

        return [
            'group' => 'detail',
            'columns' => [
                ['key' => 'label', 'label' => 'Venta', 'kind' => 'text'],
                ['key' => 'date', 'label' => 'Fecha', 'kind' => 'text'],
                ['key' => 'customer', 'label' => 'Cliente', 'kind' => 'text'],
                ['key' => 'seller', 'label' => 'Vendedor', 'kind' => 'text'],
                ...$this->amountColumns(),
            ],
            'rows' => $rows,
            'totals' => ['label' => 'Total ('.count($rows).' ventas)', ...$this->totals($sales)],
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return Result
     */
    private function bySeller(Collection $sales): array
    {
        $rows = $sales
            ->groupBy(fn (Sale $sale): string => (string) $sale->seller_id)
            ->map(fn (Collection $group): array => [
                'label' => $group->first()?->seller->name ?? '—',
                'count' => (string) $group->count(),
                ...$this->totals($group),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return [
            'group' => 'seller',
            'columns' => [
                ['key' => 'label', 'label' => 'Vendedor', 'kind' => 'text'],
                ['key' => 'count', 'label' => 'Ventas', 'kind' => 'number'],
                ...$this->amountColumns(),
            ],
            'rows' => $rows,
            'totals' => ['label' => 'Total', 'count' => (string) $sales->count(), ...$this->totals($sales)],
        ];
    }

    /**
     * Mostrador contra online, en el orden del enum.
     *
     * @param  Collection<int, Sale>  $sales
     * @return Result
     */
    private function byChannel(Collection $sales): array
    {
        $rows = [];

        foreach (SaleChannel::cases() as $channel) {
            $group = $sales->filter(fn (Sale $sale): bool => $sale->channel === $channel);

            if ($group->isEmpty()) {
                continue;
            }

            $rows[] = ['label' => $channel->getLabel(), 'count' => (string) $group->count(), ...$this->totals($group)];
        }

        return [
            'group' => 'channel',
            'columns' => [
                ['key' => 'label', 'label' => 'Canal', 'kind' => 'text'],
                ['key' => 'count', 'label' => 'Ventas', 'kind' => 'number'],
                ...$this->amountColumns(),
            ],
            'rows' => $rows,
            'totals' => ['label' => 'Total', 'count' => (string) $sales->count(), ...$this->totals($sales)],
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return Result
     */
    private function byProduct(Collection $sales): array
    {
        $items = SaleItem::query()
            ->with('product')
            ->whereIn('sale_id', $sales->pluck('id')->all())
            ->get();

        $rows = $items
            ->groupBy('product_id')
            ->map(fn (Collection $group): array => [
                'label' => (string) $group->first()?->product->name,
                'count' => (string) $group->sum('quantity'),
                ...$this->itemAmounts($group),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return [
            'group' => 'product',
            'columns' => [
                ['key' => 'label', 'label' => 'Producto', 'kind' => 'text'],
                ['key' => 'count', 'label' => 'Unidades', 'kind' => 'number'],
                ['key' => 'subtotal', 'label' => 'Ventas', 'kind' => 'money'],
                ['key' => 'cost', 'label' => 'Costo', 'kind' => 'money'],
                ['key' => 'profit', 'label' => 'Ganancia', 'kind' => 'money'],
                ['key' => 'margin', 'label' => 'Margen', 'kind' => 'percent'],
            ],
            'rows' => $rows,
            'totals' => ['label' => 'Total', 'count' => (string) $items->sum('quantity'), ...$this->itemAmounts($items)],
        ];
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return array<string, string>
     */
    private function itemAmounts(Collection $items): array
    {
        $subtotal = Money::sum($items->map(fn (SaleItem $item): string => (string) $item->subtotal)->all());
        $cost = Money::sum($items->map(fn (SaleItem $item): string => Money::mul((string) $item->unit_cost, $item->quantity))->all());
        $profit = Money::sub($subtotal, $cost);

        return [
            'subtotal' => $subtotal,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $this->percentage($profit, $subtotal),
        ];
    }

    /**
     * @return Columns
     */
    private function amountColumns(): array
    {
        return [
            ['key' => 'subtotal', 'label' => 'Subtotal', 'kind' => 'money'],
            ['key' => 'adjustment', 'label' => 'Ajustes', 'kind' => 'money'],
            ['key' => 'total', 'label' => 'Total', 'kind' => 'money'],
            ['key' => 'cost', 'label' => 'Costo', 'kind' => 'money'],
            ['key' => 'profit', 'label' => 'Ganancia', 'kind' => 'money'],
            ['key' => 'margin', 'label' => 'Margen', 'kind' => 'percent'],
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, string>
     */
    private function totals(Collection $sales): array
    {
        return $this->amounts(
            Money::sum($sales->map(fn (Sale $sale): string => (string) $sale->subtotal)->all()),
            Money::sum($sales->map(fn (Sale $sale): string => (string) $sale->adjustment)->all()),
            Money::sum($sales->map(fn (Sale $sale): string => (string) $sale->total)->all()),
            Money::sum($sales->map(fn (Sale $sale): string => (string) $sale->total_cost)->all()),
        );
    }

    /**
     * @return array<string, string>
     */
    private function amounts(string $subtotal, string $adjustment, string $total, string $cost): array
    {
        $profit = Money::sub($subtotal, $cost);

        return [
            'subtotal' => $subtotal,
            'adjustment' => $adjustment,
            'total' => $total,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $this->percentage($profit, $subtotal),
        ];
    }
}
