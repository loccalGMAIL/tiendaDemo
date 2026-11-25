<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Medios de pago iniciales (docs/decisiones.md). Repetible: busca por nombre
     * y no pisa lo que el administrador ya haya cambiado desde el panel.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Efectivo', 'installments' => 1, 'adjustment_percent' => '-10.00', 'is_cash' => true],
            ['name' => 'Transferencia', 'installments' => 1, 'adjustment_percent' => '0.00'],
            ['name' => 'Débito', 'installments' => 1, 'adjustment_percent' => '0.00'],
            ['name' => 'Tarjeta 1 cuota', 'installments' => 1, 'adjustment_percent' => '0.00'],
            ['name' => 'Tarjeta 3 cuotas', 'installments' => 3, 'adjustment_percent' => '15.00'],
            ['name' => 'Tarjeta 6 cuotas', 'installments' => 6, 'adjustment_percent' => '30.00'],
        ];

        foreach ($methods as $position => $method) {
            PaymentMethod::query()->firstOrCreate(
                ['name' => $method['name']],
                [...$method, 'active' => true, 'position' => $position],
            );
        }
    }
}
