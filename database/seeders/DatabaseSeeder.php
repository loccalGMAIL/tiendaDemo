<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * No enciende módulos: en un cliente nuevo se habilitan según su abono
     * (modules:enable). Los datos de demostración llegan con la Fase 7.
     */
    public function run(): void
    {
        $this->call([
            InitialUsersSeeder::class,
            PaymentMethodSeeder::class,
            CashRegisterSeeder::class,
        ]);
    }
}
