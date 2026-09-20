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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Event & Order Relationships
            |--------------------------------------------------------------------------
            */

            $table
                ->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('ticket_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('ticket_order_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('ticket_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->foreignId('attendee_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Public Identifiers
            |--------------------------------------------------------------------------
            */

            $table
                ->string('ticket_number', 80)
                ->unique();

            $table
                ->string('public_token', 80)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Secure QR
            |--------------------------------------------------------------------------
            |
            | Store only the SHA-256 hash of the QR token.
            | The raw QR token must never be stored in the database.
            |
            */

            $table
                ->char('qr_token_hash', 64)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Ticket Holder
            |--------------------------------------------------------------------------
            */

            $table
                ->string('holder_name', 255);

            $table
                ->string('holder_phone', 30)
                ->nullable();

            $table
                ->string('holder_email', 255)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Price Snapshot
            |--------------------------------------------------------------------------
            |
            | Store the actual price paid for this ticket so future changes to the
            | TicketType price do not change historical ticket/payment records.
            |
            */

            $table
                ->decimal('price', 14, 2);

            $table
                ->string('currency', 3)
                ->default('TZS');

            /*
            |--------------------------------------------------------------------------
            | Ticket Status
            |--------------------------------------------------------------------------
            |
            | MVP statuses:
            | - issued
            | - used
            | - cancelled
            | - refunded
            |
            */

            $table
                ->string('status', 30)
                ->default('issued');

            /*
            |--------------------------------------------------------------------------
            | Ticket Lifecycle
            |--------------------------------------------------------------------------
            */

            $table
                ->timestampTz('issued_at')
                ->nullable();

            $table
                ->timestampTz('used_at')
                ->nullable();

            $table
                ->timestampTz('cancelled_at')
                ->nullable();

            $table
                ->timestampTz('refunded_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Additional Data
            |--------------------------------------------------------------------------
            */

            $table
                ->jsonb('metadata')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'ticket_type_id',
                'status',
            ]);

            $table->index([
                'ticket_order_id',
                'status',
            ]);

            $table->index([
                'attendee_id',
                'status',
            ]);

            $table->index('issued_at');
            $table->index('used_at');
            $table->index('cancelled_at');
            $table->index('refunded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
