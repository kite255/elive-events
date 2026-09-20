<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('announcement');
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();

            // Public / publishing
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(true);
            $table->string('public_token', 80)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            // Hero
            $table->boolean('hero_enabled')->default(true);
            $table->string('hero_image_path')->nullable();
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->boolean('hero_overlay_enabled')->default(true);
            $table->string('hero_text_alignment', 20)->default('left');
            $table->string('hero_height', 20)->default('medium');

            $table->timestamps();

            $table->unique(['event_id', 'slug']);
            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'type']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_communications');
    }
};
