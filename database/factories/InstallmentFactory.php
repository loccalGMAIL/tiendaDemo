<?php

namespace Database\Factories;

use App\Enums\InstallmentStatus;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Vencimiento suelto. Para ventas a cuenta completas (ledger, estado) usar CreateSale.
 *
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'customer_id' => Customer::factory(),
            'number' => 1,
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => '1000.00',
            'paid_amount' => '0.00',
            'cost_portion' => '600.00',
            'status' => InstallmentStatus::Pending,
        ];
    }

    public function overdue(int $days = 10): static
    {
        return $this->state(fn () => ['due_date' => now()->subDays($days)->toDateString()]);
    }
}
