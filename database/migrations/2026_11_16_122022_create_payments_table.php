<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            // Cobro hecho al vender; vacío para cobros sueltos (Cuenta corriente).
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // Copias del medio de pago al cobrar: cambiarlo después no afecta lo ya cobrado.
            $table->unsignedSmallInteger('installments')->default(1);
            $table->decimal('adjustment_percent', 5, 2)->default(0);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('adjustment_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
