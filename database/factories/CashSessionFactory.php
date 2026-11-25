<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Turno abierto sin movimientos. Para turnos completos (con su movimiento de apertura) usar
 * la acción OpenCashSession.
 *
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_id' => CashRegister::factory(),
            'opened_by' => User::factory()->seller(),
            'opened_at' => now(),
            'opening_amount' => '0.00',
        ];
    }

    public function closed(string $expected = '0.00', string $counted = '0.00'): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_by' => $attributes['opened_by'],
            'closed_at' => now(),
            'expected_amount' => $expected,
            'counted_amount' => $counted,
            'difference' => bcsub($counted, $expected, 2),
            'left_amount' => '0.00',
        ]);
    }
}
