<?php

namespace Database\Factories;

use App\Models\FinancingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingPlan>
 */
class FinancingPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'installments_count' => 3,
            'surcharge_percent' => '0.00',
            'active' => true,
            'position' => 0,
        ];
    }

    public function of(int $installments, string $surchargePercent = '0.00'): static
    {
        return $this->state(fn () => ['installments_count' => $installments, 'surcharge_percent' => $surchargePercent]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
