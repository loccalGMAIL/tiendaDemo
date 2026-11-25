<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turnos de caja (módulo Control de caja): de la apertura con su fondo al cierre con el
     * arqueo. Un turno abierto no tiene `closed_at`. Lo esperado sale del libro de movimientos
     * y se copia al cerrar, junto con lo contado y la diferencia.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('counted_amount', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            // Fondo que queda en el cajón para el próximo turno.
            $table->decimal('left_amount', 12, 2)->nullable();
            // Conteo por billete, si se hizo: {"20000": 3, "1000": 5, "coins": "350.00"}.
            $table->json('counted_bills')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
            $table->index(['cash_register_id', 'closed_at']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
