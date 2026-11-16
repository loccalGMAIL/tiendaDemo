<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas del módulo Catálogo web: existen siempre y son opcionales.
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('sku');
            $table->boolean('is_published')->default(false)->after('active');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->boolean('show_price')->default(true)->after('is_featured');
            $table->json('images')->nullable()->after('show_price');
        });

        // Los productos que ya existían reciben su slug (único) a partir del nombre.
        $used = [];

        foreach (DB::table('products')->orderBy('id')->get(['id', 'name']) as $product) {
            $base = Str::slug($product->name) ?: 'producto';
            $slug = $base;
            $suffix = 2;

            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$suffix++;
            }

            $used[] = $slug;

            DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_published', 'is_featured', 'show_price', 'images']);
        });
    }
};
