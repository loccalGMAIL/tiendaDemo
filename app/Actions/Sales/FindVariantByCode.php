<?php

namespace App\Actions\Sales;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class FindVariantByCode
{
    /**
     * Variante vendible que corresponde a un código leído (el SKU de la etiqueta): primero el
     * SKU de una variante y, si no, el del producto cuando se vende sin variantes.
     *
     * @throws ValidationException Si el código no existe o no se puede vender
     */
    public function handle(string $code): ProductVariant
    {
        $code = trim($code);

        $variant = ProductVariant::query()->with('product')->sellable()->where('sku', $code)->first();

        if ($variant !== null) {
            return $variant;
        }

        $product = Product::query()->where('sku', $code)->first();

        if ($product === null) {
            $this->fail("No hay ningún producto con el código {$code}.");
        }

        $sellable = $product->variants()->with('product')->sellable()->get();

        if ($sellable->count() === 1 && $sellable->first()?->isImplicit()) {
            return $sellable->first();
        }

        $this->fail($sellable->isEmpty()
            ? "«{$product->name}» no está disponible para la venta."
            : "{$code} es el código del producto: escaneá la etiqueta de la variante.");
    }

    /**
     * @return never
     *
     * @throws ValidationException
     */
    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
