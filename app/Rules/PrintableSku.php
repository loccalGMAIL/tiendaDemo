<?php

namespace App\Rules;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Barcodes\Code128;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El SKU se imprime como código de barras y el lector lo busca en productos y variantes:
 * tiene que poder codificarse (ASCII imprimible) y no repetirse en la otra tabla.
 * La unicidad dentro de su propia tabla la valida cada formulario.
 */
class PrintableSku implements ValidationRule
{
    /**
     * @param  class-string<Product|ProductVariant>  $model  Modelo al que pertenece el SKU
     */
    private function __construct(private string $model) {}

    public static function forProduct(): self
    {
        return new self(Product::class);
    }

    public static function forVariant(): self
    {
        return new self(ProductVariant::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $sku = (string) $value;

        if (! Code128::canEncode($sku)) {
            $fail('El SKU solo puede tener letras sin tilde, números y símbolos comunes (se imprime como código de barras).');

            return;
        }

        $other = $this->model === Product::class ? ProductVariant::class : Product::class;

        if ($other::query()->where('sku', $sku)->exists()) {
            $fail($this->model === Product::class
                ? 'Ese SKU ya lo usa una variante.'
                : 'Ese SKU ya lo usa un producto.');
        }
    }
}
