<?php

namespace App\Services;

/**
 * Lo que entrega el cliente en la Caja: el vuelto y los montos rápidos para «Paga con».
 * Cálculo puro (sin base de datos), con los montos como strings de Money.
 */
class Tender
{
    /**
     * Billetes por los que se redondea hacia arriba para sugerir montos («paga con $ 20.000»).
     */
    public const ROUNDINGS = ['1000', '2000', '10000', '20000', '100000'];

    /** Cuántos montos rápidos se ofrecen además del exacto. */
    public const MAX_SUGGESTIONS = 3;

    /**
     * Lee un monto escrito en la caja: «20000», «20.000», «1.234,50» o «1234.5». La coma es
     * siempre decimal; un punto es decimal solo si lo siguen uno o dos dígitos al final.
     * Devuelve null si está vacío o no es un monto válido.
     */
    public function parse(?string $typed): ?string
    {
        $text = preg_replace('/[\s$]/u', '', (string) $typed) ?? '';

        if ($text === '') {
            return null;
        }

        if (str_contains($text, ',')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif (preg_match('/\.\d{1,2}$/', $text) !== 1) {
            $text = str_replace('.', '', $text);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $text) !== 1) {
            return null;
        }

        return Money::from($text);
    }

    /**
     * Vuelto: lo entregado menos lo que se cobra. Negativo si no alcanza.
     */
    public function change(string $tendered, string $due): string
    {
        return Money::sub(Money::from($tendered), Money::from($due));
    }

    /**
     * Montos rápidos: el exacto y el monto redondeado hacia arriba a cada billete, sin
     * repetir y de menor a mayor.
     *
     * @return list<string>
     */
    public function suggestions(string $due): array
    {
        $due = Money::from($due);

        if (Money::compare($due, '0') <= 0) {
            return [];
        }

        $amounts = [];

        foreach (self::ROUNDINGS as $bill) {
            $rounded = $this->roundUp($due, $bill);

            if (Money::compare($rounded, $due) > 0 && ! in_array($rounded, $amounts, true)) {
                $amounts[] = $rounded;
            }
        }

        usort($amounts, fn (string $a, string $b): int => Money::compare($a, $b));

        return [$due, ...array_slice($amounts, 0, self::MAX_SUGGESTIONS)];
    }

    /**
     * El menor múltiplo del billete que alcanza para pagar el monto.
     */
    private function roundUp(string $amount, string $bill): string
    {
        $units = bcdiv($amount, $bill, 0);

        if (Money::compare(bcmul($units, $bill, 2), $amount) < 0) {
            $units = bcadd($units, '1', 0);
        }

        return Money::from(bcmul($units, $bill, 2));
    }
}
