<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_template_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('organization_ticket_template_id')
                ->nullable()
                ->constrained('organization_ticket_templates')
                ->cascadeOnDelete();

            $table->foreignId('event_ticket_template_id')
                ->nullable()
                ->constrained('event_ticket_templates')
                ->cascadeOnDelete();

            $table->string('asset_type', 32);

            $table->string('label');

            $table->string('file_path');

            $table->string('mime_type', 100);

            $table->unsignedInteger('width')
                ->nullable();

            $table->unsignedInteger('height')
                ->nullable();

            $table->jsonb('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'organization_id',
                'asset_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_template_assets');
    }
};
