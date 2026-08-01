<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_variants', function (Blueprint $table) {
            $table->string('size')->nullable()->after('name');
            $table->string('color_name')->nullable()->after('size');
            $table->string('color_code', 20)->nullable()->after('color_name');

            $table->decimal('price', 12, 2)
                ->default(0)
                ->after('stock_quantity');

            $table->string('currency', 3)
                ->default('TZS')
                ->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_variants', function (Blueprint $table) {
            $table->dropColumn([
                'size',
                'color_name',
                'color_code',
                'price',
                'currency',
            ]);
        });
    }
};