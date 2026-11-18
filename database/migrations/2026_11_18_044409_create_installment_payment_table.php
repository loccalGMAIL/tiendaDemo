<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imputación de los cobros a los vencimientos: cuánto de cada cobro cubrió cada uno.
     */
    public function up(): void
    {
        Schema::create('installment_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['payment_id', 'installment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payment');
    }
};
