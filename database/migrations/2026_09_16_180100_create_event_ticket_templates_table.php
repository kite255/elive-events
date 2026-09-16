<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_ticket_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('source_organization_template_id')
                ->nullable()
                ->constrained('organization_ticket_templates')
                ->nullOnDelete();

            $table->string('name');

            $table->unsignedInteger('width')
                ->default(1080);

            $table->unsignedInteger('height')
                ->default(1350);

            $table->string('thumbnail_path')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->boolean('is_default')
                ->default(false);

            $table->timestamps();

            $table->index([
                'event_id',
                'ticket_type_id',
                'is_active',
            ]);

            $table->index([
                'event_id',
                'is_default',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ticket_templates');
    }
};
