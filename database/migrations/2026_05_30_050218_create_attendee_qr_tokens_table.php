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
        Schema::create('attendee_qr_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('token_hash')->unique();
            $table->string('token_last4', 4)->nullable();

            $table->dateTime('expires_at')->nullable();
            $table->dateTime('used_at')->nullable();

            $table->timestamps();

            $table->index('attendee_id');
            $table->index('expires_at');
            $table->index('used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendee_qr_tokens');
    }
};