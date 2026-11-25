<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canal de la venta: mostrador (la Caja) u online (lo que se cierra por WhatsApp y se carga
     * desde «Nueva venta»; a futuro, los pedidos del catálogo). Las ventas que ya existían
     * quedan como mostrador: es lo único que se puede suponer de ellas.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('channel')->default('counter')->after('seller_id');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};
