<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_upgrades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('ticket_order_id')
                ->constrained('ticket_orders')
                ->restrictOnDelete();

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->restrictOnDelete();

            $table->foreignId('from_ticket_type_id')
                ->constrained('ticket_types')
                ->restrictOnDelete();

            $table->foreignId('to_ticket_type_id')
                ->constrained('ticket_types')
                ->restrictOnDelete();

            $table->string('public_token', 48)->unique();
            $table->string('reference', 80)->unique();

            $table->decimal('original_price', 15, 2);
            $table->decimal('target_price', 15, 2);
            $table->decimal('upgrade_amount', 15, 2);
            $table->string('currency', 3);

            $table->string('status', 30)->default('pending')->index();

            $table->foreignId('initiated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();

            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('platform_commission_rate', 8, 4)->nullable();
            $table->decimal('platform_commission_amount', 15, 2)->nullable();
            $table->decimal('gateway_fee_rate', 8, 4)->nullable();
            $table->decimal('gateway_fee_amount', 15, 2)->nullable();
            $table->decimal('total_charges', 15, 2)->nullable();
            $table->decimal('organizer_net_amount', 15, 2)->nullable();
            $table->timestampTz('financial_snapshot_at')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index(['ticket_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_upgrades');
    }
};
