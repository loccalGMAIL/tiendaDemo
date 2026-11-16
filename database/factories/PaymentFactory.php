<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'date' => now()->toDateString(),
            'payment_method_id' => PaymentMethod::factory(),
            'installments' => 1,
            'adjustment_percent' => '0.00',
            'base_amount' => '1000.00',
            'adjustment_amount' => '0.00',
            'amount' => '1000.00',
            'collected_by' => User::factory()->seller(),
        ];
    }
}
