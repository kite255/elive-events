<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('event_day_id')
                ->constrained('event_days')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('session_type')
                ->default('session');

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->string('venue_name')->nullable();

            $table->unsignedInteger('capacity')->nullable();

            $table->boolean('requires_registration')
                ->default(false);

            $table->boolean('registration_is_open')
                ->default(true);

            $table->boolean('requires_check_in')
                ->default(false);

            $table->string('status')
                ->default('active');

            $table->unsignedInteger('display_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'event_id',
                'event_day_id',
                'status',
            ]);

            $table->index([
                'event_day_id',
                'starts_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
