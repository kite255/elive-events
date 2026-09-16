<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_template_pages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_ticket_template_id')
                ->nullable()
                ->constrained('organization_ticket_templates')
                ->cascadeOnDelete();

            $table->foreignId('event_ticket_template_id')
                ->nullable()
                ->constrained('event_ticket_templates')
                ->cascadeOnDelete();

            $table->unsignedInteger('page_number');

            $table->string('name');

            $table->string('background_image_path')
                ->nullable();

            $table->jsonb('definition')
                ->default(json_encode([
                    'version' => 1,
                    'elements' => [],
                ]));

            $table->timestamps();

            $table->unique(
                [
                    'organization_ticket_template_id',
                    'page_number',
                ],
                'org_ticket_template_page_unique'
            );

            $table->unique(
                [
                    'event_ticket_template_id',
                    'page_number',
                ],
                'event_ticket_template_page_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_template_pages');
    }
};
