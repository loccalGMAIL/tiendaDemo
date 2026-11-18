<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Cálculo puro del cronograma de cuotas (sin base de datos), módulo Cuotas propias. Lo usan la
 * venta real, el listener que arma los vencimientos y la vista previa del formulario, así los
 * números coinciden.
 *
 * Reglas: la cuota es lo financiado dividido la cantidad, redondeada hacia abajo a centavos, y
 * la última absorbe la diferencia; los vencimientos son mensuales y consecutivos; cada cuota
 * lleva la parte del costo que le corresponde (la última absorbe el redondeo).
 */
class InstallmentSchedule
{
    public const RULE_FIXED_DAY = 'fixed_day';

    public const RULE_SAME_DAY = 'same_day';

    /**
     * Recargo de financiación sobre lo que queda a cuenta.
     */
    public function surcharge(string $base, string $percent): string
    {
        return Money::percentOf($base, $percent);
    }

    /**
     * Parte del costo de la venta que corresponde a lo financiado (sin recargo).
     */
    public function financedCost(string $totalCost, string $base, string $subtotal): string
    {
        if (Money::compare($subtotal, '0') === 0) {
            return '0.00';
        }

        return Money::round(bcdiv(bcmul($totalCost, $base, 8), $subtotal, 8));
    }

    /**
     * Día del mes al que se anclan los vencimientos: el fijo, o el de la venta.
     */
    public function anchorDay(CarbonInterface $saleDate, string $rule, int $fixedDay): int
    {
        return $rule === self::RULE_SAME_DAY ? $saleDate->day : $fixedDay;
    }

    /**
     * Fecha de la primera cuota. Con día fijo se elige el mes (AAAA-MM; por defecto el siguiente
     * al de la venta); con «mismo día», un mes después de la venta. En los meses más cortos vence
     * el último día.
     */
    public function firstDueDate(CarbonInterface $saleDate, string $rule, int $fixedDay, ?string $month = null): Carbon
    {
        $monthStart = $rule === self::RULE_FIXED_DAY && $this->validMonth($month)
            ? Carbon::createFromFormat('!Y-m', (string) $month)
            : null;

        $monthStart ??= Carbon::instance($saleDate)->startOfMonth()->addMonthNoOverflow();

        return $this->onDay($monthStart, $this->anchorDay($saleDate, $rule, $fixedDay));
    }

    /**
     * Si el mes tiene la forma AAAA-MM de un mes real.
     */
    public function validMonth(?string $month): bool
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }

    /**
     * Cronograma: una fila por cuota con su número, vencimiento, monto y porción de costo.
     *
     * @return list<array{number: int, due_date: Carbon, amount: string, cost_portion: string}>
     */
    public function build(string $financed, int $count, CarbonInterface $firstDueDate, int $anchorDay, string $costToSpread): array
    {
        $first = Carbon::instance($firstDueDate)->startOfDay();
        $count = max(1, $count);
        // bcdiv trunca: la cuota queda redondeada hacia abajo a centavos.
        $installment = bcdiv($financed, (string) $count, 2);

        $rows = [];
        $amountSoFar = '0.00';
        $costSoFar = '0.00';

        for ($number = 1; $number <= $count; $number++) {
            $isLast = $number === $count;
            $amount = $isLast ? Money::sub($financed, $amountSoFar) : $installment;

            $cost = $isLast
                ? Money::sub($costToSpread, $costSoFar)
                : (Money::compare($financed, '0') === 0 ? '0.00' : Money::round(bcdiv(bcmul($costToSpread, $amount, 8), $financed, 8)));

            $rows[] = [
                'number' => $number,
                // La primera cuota vence justo en la fecha pedida; las demás, el día de anclaje de cada mes.
                'due_date' => $number === 1
                    ? $first->copy()
                    : $this->onDay($first->copy()->startOfMonth()->addMonthsNoOverflow($number - 1), $anchorDay),
                'amount' => $amount,
                'cost_portion' => $cost,
            ];

            $amountSoFar = Money::add($amountSoFar, $amount);
            $costSoFar = Money::add($costSoFar, $cost);
        }

        return $rows;
    }

    /**
     * El día pedido del mes de la fecha, o el último si el mes es más corto.
     */
    private function onDay(Carbon $date, int $day): Carbon
    {
        $start = $date->copy()->startOfMonth();

        return $start->copy()->day(min($day, $start->daysInMonth));
    }
}
