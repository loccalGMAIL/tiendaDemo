<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => 1,
            'balance' => 1,
            'reason' => 'Recuento',
        ];
    }
}
