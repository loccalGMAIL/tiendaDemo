<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'description' => fake()->words(3, true),
            'quantity' => 1,
            'unit_cost' => '600.00',
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
        ];
    }
}
