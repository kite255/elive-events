<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table
                ->foreignId('ticket_order_id')
                ->nullable()
                ->after('attendee_id')
                ->constrained('ticket_orders')
                ->nullOnDelete();

            $table->index([
                'ticket_order_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign([
                'ticket_order_id',
            ]);

            $table->dropIndex([
                'ticket_order_id',
                'status',
            ]);

            $table->dropColumn(
                'ticket_order_id'
            );
        });
    }
};