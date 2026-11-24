<?php

namespace App\Services\Barcodes;

/**
 * Dibuja un código Code 128 como SVG en milímetros. Sin antialias (`crispEdges`) para
 * que las impresoras térmicas lo impriman nítido.
 */
class BarcodeSvg
{
    /** Ancho máximo de un módulo (la barra más fina): evita códigos enormes si el texto es corto. */
    private const MAX_MODULE_MM = 0.4;

    /** Zona de silencio a cada lado, en módulos. */
    private const QUIET_MODULES = 10;

    public function __construct(private Code128 $encoder = new Code128) {}

    /**
     * @param  float  $maxWidthMm  Ancho disponible: el código se achica para entrar.
     */
    public function render(string $code, float $heightMm, float $maxWidthMm): string
    {
        $widths = $this->encoder->widths($code);
        $total = array_sum($widths) + 2 * self::QUIET_MODULES;
        $module = min(self::MAX_MODULE_MM, $maxWidthMm / $total);

        $path = '';
        $x = self::QUIET_MODULES;

        foreach ($widths as $position => $width) {
            if ($position % 2 === 0) {
                $path .= "M{$x} 0h{$width}v1h-{$width}z";
            }

            $x += $width;
        }

        $widthMm = round($total * $module, 3);
        $heightMm = round($heightMm, 3);

        return '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="'.e($code).'" '
            ."width=\"{$widthMm}mm\" height=\"{$heightMm}mm\" viewBox=\"0 0 {$total} 1\" preserveAspectRatio=\"none\" "
            ."shape-rendering=\"crispEdges\"><path d=\"{$path}\" fill=\"#000\"/></svg>";
    }
}
