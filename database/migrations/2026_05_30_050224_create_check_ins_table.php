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
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('check_in_point_id')
                ->nullable()
                ->constrained('check_in_points')
                ->nullOnDelete();

            $table->foreignId('checked_in_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('method')->default('qr');

            $table->dateTime('checked_in_at');

            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['attendee_id', 'check_in_point_id']);
            $table->index(['event_id', 'checked_in_at']);
            $table->index(['event_id', 'method']);
            $table->index('checked_in_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};