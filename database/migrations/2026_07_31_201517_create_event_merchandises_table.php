<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_merchandises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->string('image_path')->nullable();
            $table->boolean('show_image')->default(true);

            $table->string('selection_type')->default('optional');
            $table->timestamp('selection_opens_at')->nullable();
            $table->timestamp('selection_closes_at')->nullable();

            $table->unsignedInteger('maximum_per_attendee')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();

            $table->index([
                'event_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_merchandises');
    }
};
