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
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('attendee_categories')
                ->nullOnDelete();

            $table->foreignId('badge_type_id')
                ->nullable()
                ->constrained('badge_types')
                ->nullOnDelete();

            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('organization_name')->nullable();
            $table->string('position')->nullable();

            $table->string('status')->default('registered');
            $table->string('registration_source')->default('manual');

            $table->dateTime('registered_at')->nullable();

            $table->string('badge_number')->nullable();
            $table->string('badge_path')->nullable();

            $table->dateTime('checked_in_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'phone']);
            $table->index(['event_id', 'email']);
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'checked_in_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};