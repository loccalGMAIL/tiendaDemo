<?php

namespace App\Actions\Variants;

use App\Models\ProductVariant;

class AssignMissingVariantSkus
{
    /**
     * Da SKU (PROD-00012-01, -02…) a las variantes con nombre que no lo tienen, para que
     * puedan llevar código de barras. No toca las que ya tienen uno.
     *
     * @return int Cantidad de variantes completadas
     */
    public function handle(): int
    {
        $assigned = 0;

        ProductVariant::query()
            ->where('name', '!=', '')
            ->where(fn ($query) => $query->whereNull('sku')->orWhere('sku', ''))
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id')
            ->each(function (ProductVariant $variant) use (&$assigned): void {
                $variant->sku = ProductVariant::nextSkuFor($variant->product_id);
                $variant->save();
                $assigned++;
            });

        return $assigned;
    }
}
