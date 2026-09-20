<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('event_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('attendee_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('payment_gateway_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('reference', 80)->unique();
            $table->string('provider_reference', 150)->nullable()->index();
            $table->string('provider_tracking_id', 150)->nullable()->unique();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TZS');

            $table->string('status', 30)->default('pending')->index();
            $table->string('payment_method', 80)->nullable();

            $table->string('description', 255)->nullable();

            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('paid_at')->nullable()->index();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['event_id', 'status']);
            $table->index(['attendee_id', 'status']);
            $table->index(['event_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
