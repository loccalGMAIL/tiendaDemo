<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Como el alta real, todo producto creado con la factory nace con su variante
 * implícita (usar CreateProduct para probar la asignación automática del SKU).
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'name' => fake()->unique()->words(3, true),
            'brand' => fake()->company(),
            'price' => '1000.00',
            'cost' => '600.00',
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if (! $product->variants()->exists()) {
                $product->variants()->create(['name' => '', 'position' => 0]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function priced(string $price, string $cost = '0.00'): static
    {
        return $this->state(fn () => ['price' => $price, 'cost' => $cost]);
    }
}
