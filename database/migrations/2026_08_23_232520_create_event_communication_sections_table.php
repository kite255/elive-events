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
        Schema::create(
            'event_communication_sections',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'event_communication_id'
                )
                    ->constrained(
                        'event_communications'
                    )
                    ->cascadeOnDelete();

                $table->string('title');

                $table->longText(
                    'content'
                )->nullable();

                $table->string(
                    'image_path'
                )->nullable();

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->timestamps();

                $table->index([
                    'event_communication_id',
                    'sort_order',
                ]);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'event_communication_sections'
        );
    }
};
