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
        Schema::create('communication_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            // sms, email, whatsapp
            $table->string('channel');

            // Mostly used for email
            $table->string('subject')->nullable();

            // Message body with placeholders like {{ attendee_name }}, {{ event_name }}, {{ rsvp_link }}
            $table->text('body');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['organization_id', 'channel']);
            $table->index(['channel', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_templates');
    }
};