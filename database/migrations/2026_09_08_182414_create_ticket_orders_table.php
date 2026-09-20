<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('attendee_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->string('order_number', 50)
                ->unique();

            $table
                ->string('buyer_name', 255);

            $table
                ->string('buyer_phone', 30)
                ->nullable();

            $table
                ->string('buyer_email', 255)
                ->nullable();

            $table
                ->unsignedInteger('quantity')
                ->default(1);

            $table
                ->decimal('subtotal', 14, 2)
                ->default(0);

            $table
                ->decimal('discount_amount', 14, 2)
                ->default(0);

            $table
                ->decimal('total', 14, 2)
                ->default(0);

            $table
                ->string('currency', 3)
                ->default('TZS');

            $table
                ->string('status', 30)
                ->default('pending');

            $table
                ->timestampTz('paid_at')
                ->nullable();

            $table
                ->timestampTz('expires_at')
                ->nullable();

            $table
                ->jsonb('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'attendee_id',
                'status',
            ]);

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_orders');
    }
};