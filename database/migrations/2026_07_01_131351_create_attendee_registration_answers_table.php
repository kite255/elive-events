<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_registration_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_registration_field_id')
                ->constrained('event_registration_fields')
                ->cascadeOnDelete();

            $table->longText('value')->nullable();

            $table->timestamps();

            $table->unique(['attendee_id', 'event_registration_field_id'], 'attendee_field_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_registration_answers');
    }
};