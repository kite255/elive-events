<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table): void {
            $table->foreignId('merchandise_order_id')->nullable()->constrained('merchandise_orders')->nullOnDelete();
            $table->unique(['merchandise_order_id', 'purpose', 'channel'], 'comm_logs_merch_purpose_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table): void {
            $table->dropUnique('comm_logs_merch_purpose_channel_unique');
            $table->dropConstrainedForeignId('merchandise_order_id');
        });
    }
};
