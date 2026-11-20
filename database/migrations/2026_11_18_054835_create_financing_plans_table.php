<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planes de financiación del propio comercio (módulo Cuotas propias).
     */
    public function up(): void
    {
        Schema::create('financing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('installments_count');
            // Recargo sobre lo que se financia (0 = sin recargo).
            $table->decimal('surcharge_percent', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financing_plans');
    }
};
