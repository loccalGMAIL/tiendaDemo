<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El recargo de financiación de la venta (módulo Cuotas propias): el monto y el porcentaje
     * del plan que se copiaron al vender. Las columnas existen siempre y valen cero sin plan.
     * `financing_plan_id`, `installments_count` y `first_due_date` ya estaban.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('financing_surcharge', 12, 2)->default(0)->after('financed_amount');
            $table->decimal('financing_surcharge_percent', 5, 2)->default(0)->after('financing_surcharge');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['financing_surcharge', 'financing_surcharge_percent']);
        });
    }
};
