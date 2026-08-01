<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_merchandises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_merchandise_id')
                ->constrained('event_merchandises')
                ->cascadeOnDelete();

            $table->foreignId('merchandise_variant_id')
                ->nullable()
                ->constrained('merchandise_variants')
                ->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->string('status')->default('reserved');
            $table->string('selection_source')
                ->default('public_registration');

            $table->timestamp('selected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('distributed_at')->nullable();

            $table->foreignId('distributed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique([
                'attendee_id',
                'event_merchandise_id',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'merchandise_variant_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_merchandises');
    }
};
