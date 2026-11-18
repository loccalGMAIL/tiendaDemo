<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Orden de compra sin ítems. Para órdenes completas usar la acción CreatePurchaseOrder.
 *
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::Draft,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Issued, 'issued_at' => now()]);
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => PurchaseOrderStatus::Received, 'issued_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Anulada de prueba',
        ]);
    }
}
