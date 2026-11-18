<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => fn (array $attributes): int => (int) Product::query()->findOrFail($attributes['product_id'])->implicitVariant->getKey(),
            'quantity' => 10,
            'unit_cost' => '100.00',
            'subtotal' => '1000.00',
        ];
    }
}
