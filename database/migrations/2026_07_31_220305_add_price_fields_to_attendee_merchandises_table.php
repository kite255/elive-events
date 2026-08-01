<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_merchandises', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)
                ->default(0)
                ->after('quantity');

            $table->decimal('total_price', 12, 2)
                ->default(0)
                ->after('unit_price');

            $table->string('currency', 3)
                ->default('TZS')
                ->after('total_price');

            $table->string('payment_status')
                ->default('not_required')
                ->after('currency');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendee_merchandises', function (Blueprint $table) {
            $table->dropIndex([
                'payment_status',
            ]);

            $table->dropColumn([
                'unit_price',
                'total_price',
                'currency',
                'payment_status',
            ]);
        });
    }
};