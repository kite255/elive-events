<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('merchandise_products', function (Blueprint $table) {
            $table->foreignId('event_merchandise_id')->nullable()->constrained('event_merchandises')->restrictOnDelete();
        });
        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->foreignId('merchandise_variant_id')->nullable()->constrained('merchandise_variants')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchandise_variant_id');
        });
        Schema::table('merchandise_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_merchandise_id');
        });
    }
};
