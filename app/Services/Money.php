<?php

namespace App\Services;

/**
 * Aritmética de dinero con bcmath. Los montos son strings con 2 decimales
 * (`decimal(12,2)`); nunca float. Redondeo a centavos: mitad hacia afuera del cero.
 */
final class Money
{
    private const SCALE = 2;

    /**
     * Normaliza cualquier número a un string con 2 decimales (redondeado).
     */
    public static function from(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        // Los float (p. ej. un SUM de SQLite) se pasan por texto con precisión fija.
        $text = is_float($value) ? number_format($value, 8, '.', '') : (string) $value;

        return self::round($text);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * @param  list<string>  $values
     */
    public static function sum(array $values): string
    {
        return array_reduce($values, fn (string $carry, string $value): string => self::add($carry, $value), '0.00');
    }

    /**
     * Multiplica un monto por una cantidad o factor y redondea a centavos.
     */
    public static function mul(string $amount, string|int $factor): string
    {
        return self::round(bcmul($amount, (string) $factor, 8));
    }

    /**
     * Porcentaje de un monto redondeado a centavos (positivo = recargo, negativo = descuento).
     */
    public static function percentOf(string $amount, string $percent): string
    {
        return self::round(bcdiv(bcmul($amount, $percent, 8), '100', 8));
    }

    /**
     * Compara a nivel centavos: -1, 0 o 1.
     */
    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isNegative(string $amount): bool
    {
        return self::compare($amount, '0') < 0;
    }

    /**
     * Texto para mostrar en pantalla ($ 1.234,56). Solo presentación: no se opera con el resultado.
     */
    public static function format(string $amount): string
    {
        return '$ '.number_format((float) $amount, self::SCALE, ',', '.');
    }

    /**
     * Redondea a 2 decimales, mitad hacia afuera del cero (0,4995 → 0,50; −10,005 → −10,01).
     */
    public static function round(string $value): string
    {
        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        $rounded = bcadd(bcadd($value, $half, 8), '0', self::SCALE);

        // bcmath puede dejar un cero negativo.
        return $rounded === '-0.00' ? '0.00' : $rounded;
    }
}
