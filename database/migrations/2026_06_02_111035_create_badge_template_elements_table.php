<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_template_elements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('badge_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type')->default('text');
            // text, image, qr, shape

            $table->string('field_key')->nullable();
            // full_name, event_name, category, badge_type, badge_number, organization_name, position, qr_code, logo

            $table->string('label');

            $table->unsignedInteger('x')->default(0);
            $table->unsignedInteger('y')->default(0);
            $table->unsignedInteger('width')->default(200);
            $table->unsignedInteger('height')->default(40);

            $table->unsignedInteger('font_size')->default(16);
            $table->string('font_weight')->default('700');
            $table->string('color')->default('#0B1F3A');
            $table->string('background_color')->nullable();

            $table->string('text_align')->default('center');
            // left, center, right

            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(1);

            $table->timestamps();

            $table->index(['badge_template_id', 'field_key']);
            $table->index(['badge_template_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_template_elements');
    }
};