<?php

namespace App\Services\Reports;

use App\Services\Money;
use Illuminate\Support\Carbon;

/**
 * Base de los informes: un informe recibe filtros y devuelve columnas, filas y
 * totales. La pantalla y la versión imprimible usan el mismo resultado, así los
 * números coinciden.
 */
abstract class Report
{
    /**
     * Período pedido; sin fechas válidas, el mes actual. Si vienen al revés se ordenan.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function period(array $filters): array
    {
        $from = self::date($filters['from'] ?? null) ?? now()->startOfMonth();
        $to = self::date($filters['to'] ?? null) ?? now()->endOfMonth();

        return $from->greaterThan($to) ? [$to->startOfDay(), $from->startOfDay()] : [$from->startOfDay(), $to->startOfDay()];
    }

    /**
     * Porcentaje del monto que representa la parte, a 2 decimales (0 si no hay base).
     */
    protected function percentage(string $part, string $base): string
    {
        if (Money::compare($base, '0') === 0) {
            return '0.00';
        }

        return Money::round(bcdiv(bcmul($part, '100', 8), $base, 8));
    }

    protected function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
