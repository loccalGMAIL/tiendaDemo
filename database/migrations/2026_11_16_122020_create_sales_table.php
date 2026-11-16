<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('financed_amount', 12, 2)->default(0);
            // Los usa el módulo Cuotas propias.
            $table->unsignedBigInteger('financing_plan_id')->nullable();
            $table->unsignedSmallInteger('installments_count')->nullable();
            $table->date('first_due_date')->nullable();
            $table->string('status')->default('paid');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
