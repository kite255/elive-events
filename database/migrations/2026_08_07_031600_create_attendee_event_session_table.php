<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'attendee_event_session',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('attendee_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('event_session_id')
                    ->constrained('event_sessions')
                    ->cascadeOnDelete();

                $table->string('status')
                    ->default('registered');

                $table->string('selection_source')
                    ->nullable();

                $table->dateTime('selected_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'attendee_id',
                    'event_session_id',
                ]);

                $table->index([
                    'event_session_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'attendee_event_session'
        );
    }
};
