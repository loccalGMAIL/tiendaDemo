<?php

namespace Database\Factories;

use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Venta de contado ya cobrada. Para ventas completas (ítems, cobros, costos)
 * usar la acción CreateSale; la factory sirve para armar totales rápidos.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->seller(),
            'channel' => SaleChannel::Counter,
            'date' => now()->toDateString(),
            'subtotal' => '1000.00',
            'adjustment' => '0.00',
            'total' => '1000.00',
            'total_cost' => '600.00',
            'down_payment' => '1000.00',
            'financed_amount' => '0.00',
            'status' => SaleStatus::Paid,
        ];
    }

    public function totalling(string $total, string $cost = '0.00'): static
    {
        return $this->state(fn () => [
            'subtotal' => $total,
            'total' => $total,
            'total_cost' => $cost,
            'down_payment' => $total,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn () => ['channel' => SaleChannel::Online]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => SaleStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Anulada de prueba',
        ]);
    }
}
