<?php

namespace Database\Factories;

use App\Enums\AccountMovementType;
use App\Models\AccountMovement;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountMovement>
 */
class AccountMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'date' => now()->toDateString(),
            'type' => AccountMovementType::Sale,
            'description' => 'Venta de prueba',
            'debit' => '1000.00',
            'credit' => '0.00',
            'balance' => '1000.00',
        ];
    }
}
