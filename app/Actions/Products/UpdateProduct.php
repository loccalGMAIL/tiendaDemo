<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class UpdateProduct
{
    /**
     * Un SKU o un slug vacíos al editar no los borran: se conservan los que ya tenía.
     * Las imágenes del catálogo que se quitaron se borran del disco.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): Product
    {
        foreach (['sku', 'slug'] as $key) {
            if (blank($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }

        $previousImages = $product->imagePaths();

        $product->update($data);

        if (array_key_exists('images', $data)) {
            foreach (array_diff($previousImages, $product->imagePaths()) as $removed) {
                Storage::disk('public')->delete($removed);
            }
        }

        return $product;
    }
}
