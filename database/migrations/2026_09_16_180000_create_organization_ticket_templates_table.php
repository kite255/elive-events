<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_ticket_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->text('description')
                ->nullable();

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
                'organization_id',
                'is_active',
            ]);

            $table->index([
                'organization_id',
                'is_default',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_ticket_templates');
    }
};
