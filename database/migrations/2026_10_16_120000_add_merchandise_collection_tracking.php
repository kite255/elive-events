<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table): void {
            $table->string('fulfillment_status')->default('not_ready');
            $table->timestamp('collected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merchandise_orders', function (Blueprint $table): void {
            $table->dropColumn(['fulfillment_status', 'collected_at']);
        });
    }
};
