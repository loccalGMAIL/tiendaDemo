<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Los medios de pago llegan con la Fase 3.
     */
    public function run(): void
    {
        $this->call(InitialUsersSeeder::class);
    }
}
