<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('type');
            // Con signo: positivo suma, negativo resta.
            $table->integer('quantity');
            // Stock de la variante después del movimiento.
            $table->integer('balance');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
