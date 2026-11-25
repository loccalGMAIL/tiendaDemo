<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use Illuminate\Database\Seeder;

class CashRegisterSeeder extends Seeder
{
    /**
     * Una caja para empezar (módulo Control de caja). Repetible: si ya hay alguna, no hace nada.
     * Existe aunque el módulo esté apagado, como las demás tablas de los módulos.
     */
    public function run(): void
    {
        if (CashRegister::query()->exists()) {
            return;
        }

        CashRegister::query()->create(['name' => 'Caja 1', 'active' => true, 'position' => 0]);
    }
}
