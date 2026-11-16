<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'installments' => 1,
            'adjustment_percent' => '0.00',
            'active' => true,
            'position' => 0,
        ];
    }

    public function withAdjustment(string $percent, int $installments = 1): static
    {
        return $this->state(fn () => ['adjustment_percent' => $percent, 'installments' => $installments]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
