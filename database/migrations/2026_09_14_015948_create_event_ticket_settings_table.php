<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_ticket_settings', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('event_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->boolean('ticket_sales_enabled')
                ->default(true);

            $table
                ->unsignedSmallInteger('reservation_minutes')
                ->default(15);

            $table
                ->unsignedSmallInteger('max_tickets_per_order')
                ->default(10);

            $table
                ->boolean('allow_guest_checkout')
                ->default(true);

            $table
                ->timestampTz('sales_start_at')
                ->nullable();

            $table
                ->timestampTz('sales_end_at')
                ->nullable();

            $table
                ->jsonb('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'ticket_sales_enabled',
                'sales_start_at',
                'sales_end_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ticket_settings');
    }
};