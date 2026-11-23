<?php

namespace App\Http\Controllers;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Barcodes\BarcodeSvg;
use App\Services\Barcodes\Code128;
use App\Services\Barcodes\LabelLayout;
use App\Services\Money;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Etiquetas con código de barras (módulo Códigos de barras, solo administrador): HTML con
 * estilos de impresión según el formato configurado (rollo o hoja), sin librería de PDF.
 *
 * Recibe `v=12:3,15:1` (variante:cantidad) y, en hojas, `start` (posición de la primera
 * etiqueta, para reutilizar una hoja ya empezada).
 */
class BarcodeLabelsPrintController extends Controller
{
    /** Cantidad máxima de etiquetas por pedido. */
    public const MAX_LABELS = 2000;

    /** Cantidad máxima de etiquetas de una misma variante. */
    public const MAX_PER_VARIANT = 500;

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl() ?? '/');
        }

        abort_unless($user->isAdmin(), 403);

        $layout = LabelLayout::fromSettings(Modules::settings(Module::Barcodes));
        $requested = self::parseSelection((string) $request->query('v', ''));

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', array_keys($requested))
            ->get()
            ->keyBy('id');

        $labels = [];
        $withoutCode = [];
        $svg = new BarcodeSvg;
        $padding = 1.0;
        $barHeight = round(max(6.0, $layout->height * 0.34), 2);

        foreach ($requested as $id => $quantity) {
            $variant = $variants->get($id);

            if ($variant === null) {
                continue;
            }

            $code = $variant->barcode();

            if ($code === null || ! Code128::canEncode($code)) {
                $withoutCode[] = $variant->fullName();

                continue;
            }

            $label = [
                'product' => $variant->product->name,
                'variant' => $variant->isImplicit() ? null : $variant->name,
                'price' => $layout->showPrice ? Money::format($variant->effectivePrice()) : null,
                'code' => $code,
                'svg' => $svg->render($code, $barHeight, $layout->width - 2 * $padding),
            ];

            for ($i = 0; $i < $quantity && count($labels) < self::MAX_LABELS; $i++) {
                $labels[] = $label;
            }
        }

        $skipped = $layout->isSheet() ? min(max(1, (int) $request->query('start', 1)), $layout->perPage()) - 1 : 0;
        $slots = array_merge(array_fill(0, $skipped, null), $labels);

        return view('barcodes.labels-print', [
            'business' => app(SiteContent::class)->name(),
            'layout' => $layout,
            'pages' => array_chunk($slots, $layout->perPage()),
            'total' => count($labels),
            'withoutCode' => $withoutCode,
            'fontPt' => round(max(5.5, min(9.0, $layout->height / 25 * 6.5)), 1),
            'padding' => $padding,
        ]);
    }

    /**
     * "12:3,15:1" => [12 => 3, 15 => 1]. Ignora lo que no sea numérico y limita la cantidad.
     *
     * @return array<int, int>
     */
    public static function parseSelection(string $value): array
    {
        $selection = [];

        foreach (explode(',', $value) as $pair) {
            [$id, $quantity] = array_pad(explode(':', $pair, 2), 2, '1');

            if (! ctype_digit($id) || ! ctype_digit($quantity) || (int) $id < 1 || (int) $quantity < 1) {
                continue;
            }

            $selection[(int) $id] = min(self::MAX_PER_VARIANT, ($selection[(int) $id] ?? 0) + (int) $quantity);
        }

        return $selection;
    }

    /**
     * Dirección de impresión de un pedido de etiquetas.
     *
     * @param  array<int, int>  $selection  variante => cantidad
     */
    public static function url(array $selection, int $start = 1): string
    {
        $pairs = [];

        foreach ($selection as $id => $quantity) {
            $pairs[] = "{$id}:{$quantity}";
        }

        return route('barcode-labels.print', array_filter([
            'v' => implode(',', $pairs),
            'start' => $start > 1 ? $start : null,
        ]));
    }
}
