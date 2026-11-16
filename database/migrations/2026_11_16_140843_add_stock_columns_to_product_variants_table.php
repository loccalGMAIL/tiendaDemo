<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas del módulo Stock: existen siempre y son opcionales (con el módulo apagado quedan en 0).
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('price');
            $table->unsignedInteger('min_stock')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['stock', 'min_stock']);
        });
    }
};
