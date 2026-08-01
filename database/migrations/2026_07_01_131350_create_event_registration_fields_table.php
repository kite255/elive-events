<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('label');
            $table->string('name');
            $table->string('field_type')->default('text');

            $table->text('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['event_id', 'name']);
            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_fields');
    }
};