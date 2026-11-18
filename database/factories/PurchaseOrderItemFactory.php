<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            // La variante implícita del producto elegido.
            'product_variant_id' => fn (array $attributes): int => (int) Product::query()->findOrFail($attributes['product_id'])->implicitVariant->getKey(),
            'quantity' => 10,
            'received_quantity' => 0,
        ];
    }
}
