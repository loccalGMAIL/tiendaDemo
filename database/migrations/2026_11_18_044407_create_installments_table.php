<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vencimientos: lo que un cliente debe de una venta a cuenta (módulo Cuenta corriente).
     */
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number')->default(1);
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            // Parte del costo de la venta que corresponde a este vencimiento (la usa Cuotas propias).
            $table->decimal('cost_portion', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['sale_id', 'number']);
            $table->index(['customer_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
