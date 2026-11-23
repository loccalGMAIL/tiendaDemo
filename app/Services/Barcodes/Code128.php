<?php

namespace App\Services\Barcodes;

use InvalidArgumentException;

/**
 * Codificador Code 128 (sets B y C), sin dependencias. Acepta ASCII imprimible (32 a 126)
 * y pasa al set C en los tramos de 4 o más dígitos para acortar el código.
 */
class Code128
{
    private const START_B = 104;

    private const START_C = 105;

    private const CODE_B = 100;

    private const CODE_C = 99;

    private const STOP = 106;

    /** Anchos de barra/espacio (alternados, empezando por barra) de los símbolos 0 a 106. */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /**
     * Si el texto se puede codificar: no vacío y solo ASCII imprimible.
     */
    public static function canEncode(string $text): bool
    {
        return $text !== '' && preg_match('/^[\x20-\x7E]+$/', $text) === 1;
    }

    /**
     * Valores de los símbolos: inicio, datos, dígito de control y fin.
     *
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    public function values(string $text): array
    {
        if (! self::canEncode($text)) {
            throw new InvalidArgumentException('El código solo admite caracteres ASCII imprimibles.');
        }

        $values = [];
        $set = null;
        $length = strlen($text);
        $index = 0;

        while ($index < $length) {
            $digits = $this->digitRun($text, $index);

            if ($digits >= 4 || ($set === 'C' && $digits >= 2)) {
                if ($set !== 'C') {
                    if ($digits % 2 === 1) {
                        if ($set === null) {
                            $values[] = self::START_B;
                            $set = 'B';
                        }

                        $values[] = ord($text[$index]) - 32;
                        $index++;
                        $digits--;
                    }

                    $values[] = $set === null ? self::START_C : self::CODE_C;
                    $set = 'C';
                }

                for ($pairs = intdiv($digits, 2); $pairs > 0; $pairs--) {
                    $values[] = (int) substr($text, $index, 2);
                    $index += 2;
                }

                continue;
            }

            if ($set === null) {
                $values[] = self::START_B;
            } elseif ($set === 'C') {
                $values[] = self::CODE_B;
            }

            $set = 'B';
            $values[] = ord($text[$index]) - 32;
            $index++;
        }

        $values[] = $this->checksum($values);
        $values[] = self::STOP;

        return $values;
    }

    /**
     * Anchos alternados (barra, espacio, barra…) de todo el código, sin zona de silencio.
     *
     * @return list<int>
     */
    public function widths(string $text): array
    {
        $widths = [];

        foreach ($this->values($text) as $value) {
            foreach (str_split(self::PATTERNS[$value]) as $width) {
                $widths[] = (int) $width;
            }
        }

        return $widths;
    }

    /**
     * @param  list<int>  $values  inicio y datos
     */
    private function checksum(array $values): int
    {
        $sum = $values[0];

        foreach (array_slice($values, 1) as $position => $value) {
            $sum += $value * ($position + 1);
        }

        return $sum % 103;
    }

    private function digitRun(string $text, int $from): int
    {
        $run = 0;

        while (isset($text[$from + $run]) && ctype_digit($text[$from + $run])) {
            $run++;
        }

        return $run;
    }
}
