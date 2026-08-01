<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name');

            $table->unsignedInteger('width')->default(420);
            $table->unsignedInteger('height')->default(620);

            $table->string('background_color')->default('#F8FAFC');
            $table->string('header_color')->default('#233F7E');
            $table->string('accent_color')->default('#F99A12');
            $table->string('text_color')->default('#0B1F3A');
            $table->string('footer_color')->default('#0B1F3A');

            $table->string('logo_path')->nullable();

            $table->boolean('show_category')->default(true);
            $table->boolean('show_badge_type')->default(true);
            $table->boolean('show_badge_number')->default(true);
            $table->boolean('show_organization')->default(true);
            $table->boolean('show_position')->default(true);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['event_id', 'is_default']);
            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_templates');
    }
};