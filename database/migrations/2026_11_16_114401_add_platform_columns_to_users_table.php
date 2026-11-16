<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('seller')->after('password');
            $table->boolean('active')->default(true)->after('role');
            $table->boolean('is_default_seller')->default(false)->after('active');
            $table->string('google_id')->nullable()->unique()->after('is_default_seller');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['role', 'active', 'is_default_seller', 'google_id']);
        });
    }
};
