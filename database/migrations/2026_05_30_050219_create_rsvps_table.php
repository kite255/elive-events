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
        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('response')->default('pending');
            $table->unsignedInteger('guest_count')->default(1);

            $table->dateTime('responded_at')->nullable();

            $table->string('ip_address')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique('attendee_id');
            $table->index('response');
            $table->index('responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};