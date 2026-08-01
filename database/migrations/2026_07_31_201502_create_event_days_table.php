<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->date('event_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('venue_name')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_registration_open')->default(true);
            $table->string('status')->default('active');
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'event_date']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
