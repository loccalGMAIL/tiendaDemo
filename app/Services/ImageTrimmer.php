<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Recorta el margen sobrante de una imagen guardada (el fondo liso o transparente
 * que rodea al dibujo). Sirve para los logos: un logo apaisado dentro de un cuadrado
 * blanco se ve diminuto en la barra aunque tenga la altura correcta.
 *
 * Reemplaza el archivo en el mismo lugar y es idempotente: recortar dos veces da lo
 * mismo que recortar una. Sin la extensión GD, o con una imagen que no se puede leer,
 * no toca nada.
 */
class ImageTrimmer
{
    /** Diferencia por canal (0-255) para considerar que un píxel es dibujo y no fondo. */
    private const TOLERANCE = 24;

    /** Imagen mínima que se reduce: si el recorte quita menos de esto, no se reescribe. */
    private const MIN_SAVING = 0.05;

    /**
     * @param  int  $padding  Aire que se deja alrededor del dibujo, en píxeles.
     * @return bool Si se recortó y reescribió el archivo.
     */
    public function trim(string $path, string $disk = 'public', int $padding = 24): bool
    {
        if (! extension_loaded('gd') || ! Storage::disk($disk)->exists($path)) {
            return false;
        }

        try {
            $image = @imagecreatefromstring((string) Storage::disk($disk)->get($path));
        } catch (Throwable) {
            return false;
        }

        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $box = $this->contentBox($image, $width, $height);

        if ($box === null) {
            return false;
        }

        $x = max(0, $box['left'] - $padding);
        $y = max(0, $box['top'] - $padding);
        $cropWidth = min($width, $box['right'] + $padding + 1) - $x;
        $cropHeight = min($height, $box['bottom'] + $padding + 1) - $y;

        if ($cropWidth * $cropHeight > $width * $height * (1 - self::MIN_SAVING)) {
            return false;
        }

        $cropped = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $cropWidth, 'height' => $cropHeight]);

        if ($cropped === false) {
            return false;
        }

        $encoded = $this->encode($cropped, strtolower(pathinfo($path, PATHINFO_EXTENSION)));

        if ($encoded === null) {
            return false;
        }

        Storage::disk($disk)->put($path, $encoded);

        return true;
    }

    /**
     * Rectángulo que contiene todo lo que no es fondo. El fondo es el color de la
     * esquina superior izquierda (o lo transparente). Se recorre con salto en las
     * imágenes grandes para no demorar al subirlas.
     *
     * @return array{left: int, top: int, right: int, bottom: int}|null
     */
    private function contentBox(\GdImage $image, int $width, int $height): ?array
    {
        $background = imagecolorat($image, 0, 0);
        $step = max(1, intdiv(max($width, $height), 800));
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (! $this->isBackground(imagecolorat($image, $x, $y), $background)) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        if ($right < 0) {
            return null;
        }

        // Con salto, el dibujo puede asomar hasta un píxel menos que el paso más allá de lo detectado.
        $extra = $step - 1;

        return [
            'left' => max(0, $left - $extra),
            'top' => max(0, $top - $extra),
            'right' => min($width - 1, $right + $extra),
            'bottom' => min($height - 1, $bottom + $extra),
        ];
    }

    private function isBackground(int $color, int $background): bool
    {
        $alpha = ($color >> 24) & 127;
        $backgroundAlpha = ($background >> 24) & 127;

        // Transparente contra transparente es fondo, sin mirar el color.
        if ($alpha >= 120 && $backgroundAlpha >= 120) {
            return true;
        }

        if (abs($alpha - $backgroundAlpha) > self::TOLERANCE / 2) {
            return false;
        }

        return abs((($color >> 16) & 255) - (($background >> 16) & 255)) <= self::TOLERANCE
            && abs((($color >> 8) & 255) - (($background >> 8) & 255)) <= self::TOLERANCE
            && abs(($color & 255) - ($background & 255)) <= self::TOLERANCE;
    }

    private function encode(\GdImage $image, string $extension): ?string
    {
        imagesavealpha($image, true);

        ob_start();

        $written = match ($extension) {
            'png' => imagepng($image),
            'webp' => function_exists('imagewebp') && imagewebp($image, null, 92),
            'jpg', 'jpeg' => imagejpeg($image, null, 92),
            default => false,
        };

        $contents = (string) ob_get_clean();

        return $written && $contents !== '' ? $contents : null;
    }
}
