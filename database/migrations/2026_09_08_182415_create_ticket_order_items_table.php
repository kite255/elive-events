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
        Schema::create('ticket_order_items', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('ticket_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('ticket_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table
                ->unsignedInteger('quantity');

            $table
                ->decimal('unit_price', 14, 2);

            $table
                ->decimal('subtotal', 14, 2);

            $table
                ->decimal('discount_amount', 14, 2)
                ->default(0);

            $table
                ->decimal('total', 14, 2);

            $table
                ->jsonb('metadata')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints & Indexes
            |--------------------------------------------------------------------------
            |
            | An order should contain only one line for each ticket type.
            | Quantity is stored on that single line item.
            |
            */

            $table->unique([
                'ticket_order_id',
                'ticket_type_id',
            ]);

            $table->index('ticket_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_order_items');
    }
};