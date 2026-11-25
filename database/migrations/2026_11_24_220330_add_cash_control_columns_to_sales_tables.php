<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas del módulo Control de caja en tablas del núcleo (existen siempre y son opcionales):
     * si un medio de pago entra al cajón y en qué turno se hizo cada venta y cada cobro. Los
     * medios que ya existían llamados «Efectivo» quedan marcados como efectivo.
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false)->after('adjustment_percent');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('channel')->constrained()->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('sale_id')->constrained()->restrictOnDelete();
        });

        DB::table('payment_methods')->whereRaw('LOWER(name) LIKE ?', ['%efectivo%'])->update(['is_cash' => true]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });
    }
};
