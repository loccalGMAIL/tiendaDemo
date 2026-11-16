<?php

namespace App\Actions\Products;

use App\Models\Product;

class UpdateProduct
{
    /**
     * Un SKU vacío al editar no lo borra: se conserva el que ya tenía.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): Product
    {
        if (blank($data['sku'] ?? null)) {
            unset($data['sku']);
        }

        $product->update($data);

        return $product;
    }
}
