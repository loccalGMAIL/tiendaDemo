<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    /**
     * Crea el producto con su variante implícita. Si el SKU viene vacío se
     * asigna PROD-00001, PROD-00002, … sin chocar con SKUs cargados a mano.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $sku = trim((string) ($data['sku'] ?? ''));

            $product = Product::create([...$data, 'sku' => $sku !== '' ? $sku : $this->nextSku()]);

            // Todo producto tiene al menos una variante; sin el módulo Variantes es esta, y no se ve.
            $product->variants()->create(['name' => '', 'position' => 0]);

            return $product;
        });
    }

    private function nextSku(): string
    {
        $next = ((int) Product::query()->lockForUpdate()->max('id')) + 1;

        while (Product::query()->where('sku', $this->format($next))->exists()) {
            $next++;
        }

        return $this->format($next);
    }

    private function format(int $number): string
    {
        return 'PROD-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
