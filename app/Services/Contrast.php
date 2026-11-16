<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Contraste entre dos colores según WCAG 2.x (razón de luminancias relativas).
 * Se usa para verificar que cada combinación de colores de un tema se lee bien.
 */
final class Contrast
{
    /** Mínimo para texto normal (nivel AA). */
    public const MINIMUM = 4.5;

    public static function ratio(string $foreground, string $background): float
    {
        $a = self::luminance($foreground);
        $b = self::luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidArgumentException("Color hexadecimal inválido: #{$hex}");
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
