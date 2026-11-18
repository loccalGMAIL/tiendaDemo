<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factura de compra sin ítems. Para facturas completas (costos, stock) usar CreatePurchase.
 *
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'date' => now()->toDateString(),
            'invoice_number' => fake()->unique()->numerify('A-####-########'),
            'total' => '0.00',
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
            'cancellation_reason' => 'Anulada de prueba',
        ]);
    }
}
