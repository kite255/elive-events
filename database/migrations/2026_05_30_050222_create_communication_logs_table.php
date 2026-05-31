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
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attendee_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('communication_campaign_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // sms, email, whatsapp
            $table->string('channel');

            // phone number, email address, or WhatsApp number
            $table->string('recipient')->nullable();

            $table->string('subject')->nullable();
            $table->text('message')->nullable();

            // pending, queued, sent, delivered, failed
            $table->string('status')->default('pending');

            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();

            $table->dateTime('queued_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'channel']);
            $table->index(['event_id', 'status']);
            $table->index(['attendee_id', 'status']);
            $table->index('communication_campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};