<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'type' => CashMovementType::Deposit,
            'amount' => '1000.00',
            'description' => 'Ingreso de prueba',
            'user_id' => User::factory()->seller(),
        ];
    }
}
