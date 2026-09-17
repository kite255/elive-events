<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'communication_logs',
            function (Blueprint $table): void {
                $table
                    ->foreignId('ticket_order_id')
                    ->nullable()
                    ->after('attendee_id')
                    ->constrained('ticket_orders')
                    ->nullOnDelete();

                $table
                    ->string('purpose')
                    ->nullable()
                    ->after('communication_campaign_id');

                $table
                    ->unsignedInteger('attempt_count')
                    ->default(0)
                    ->after('status');

                $table->index(
                    [
                        'ticket_order_id',
                        'purpose',
                        'channel',
                    ],
                    'communication_logs_ticket_delivery_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'communication_logs',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'communication_logs_ticket_delivery_index'
                );

                $table->dropForeign([
                    'ticket_order_id',
                ]);

                $table->dropColumn([
                    'ticket_order_id',
                    'purpose',
                    'attempt_count',
                ]);
            }
        );
    }
};
