<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_event_day', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_day_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('selection_source')
                ->default('public_registration');

            $table->timestamp('selected_at')->nullable();

            $table->timestamps();

            $table->unique([
                'attendee_id',
                'event_day_id',
            ]);

            $table->index('event_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_event_day');
    }
};
