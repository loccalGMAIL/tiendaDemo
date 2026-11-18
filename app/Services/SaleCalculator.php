<?php

namespace App\Services;

/**
 * Cálculo puro de una venta (sin base de datos). Lo usan la acción real y la
 * vista previa del formulario, así los números coinciden.
 */
class SaleCalculator
{
    /**
     * Subtotal de un ítem: cantidad × precio unitario.
     */
    public function lineSubtotal(int $quantity, string $unitPrice): string
    {
        return Money::mul($unitPrice, $quantity);
    }

    /**
     * Ajuste de un cobro: base × porcentaje del medio (positivo = recargo, negativo = descuento).
     */
    public function adjustment(string $baseAmount, string $adjustmentPercent): string
    {
        return Money::percentOf($baseAmount, $adjustmentPercent);
    }

    /**
     * @param  list<array{quantity: int, unit_price: string}>  $items
     */
    public function subtotal(array $items): string
    {
        return Money::sum(array_map(
            fn (array $item): string => $this->lineSubtotal($item['quantity'], $item['unit_price']),
            $items,
        ));
    }

    /**
     * Calcula subtotal, ajustes y total. Con un solo cobro sin base indicada, la base es el subtotal.
     * `balance` es lo que queda sin cubrir (subtotal − suma de las bases, nunca negativo): con
     * Cuenta corriente es el saldo a cuenta del cliente.
     *
     * @param  list<array{quantity: int, unit_price: string}>  $items
     * @param  list<array{adjustment_percent: string, base_amount?: string|null}>  $payments
     * @return array{
     *     subtotal: string,
     *     adjustment: string,
     *     total: string,
     *     payments: list<array{base_amount: string, adjustment_amount: string, amount: string}>,
     *     balanced: bool,
     *     balance: string,
     *     overpaid: bool
     * }
     */
    public function calculate(array $items, array $payments): array
    {
        $subtotal = $this->subtotal($items);
        $lines = [];

        foreach ($payments as $payment) {
            $base = $payment['base_amount'] ?? null;

            if (blank($base) && count($payments) === 1) {
                $base = $subtotal;
            }

            $base = Money::from($base);
            $adjustment = $this->adjustment($base, $payment['adjustment_percent']);

            $lines[] = [
                'base_amount' => $base,
                'adjustment_amount' => $adjustment,
                'amount' => Money::add($base, $adjustment),
            ];
        }

        $adjustmentTotal = Money::sum(array_column($lines, 'adjustment_amount'));
        $covered = Money::sum(array_column($lines, 'base_amount'));
        $difference = Money::sub($subtotal, $covered);

        return [
            'subtotal' => $subtotal,
            'adjustment' => $adjustmentTotal,
            'total' => Money::add($subtotal, $adjustmentTotal),
            'payments' => $lines,
            // La suma de las bases tiene que dar exactamente el subtotal.
            'balanced' => $lines !== [] && Money::compare($covered, $subtotal) === 0,
            'balance' => Money::isNegative($difference) ? '0.00' : $difference,
            'overpaid' => Money::isNegative($difference),
        ];
    }
}
