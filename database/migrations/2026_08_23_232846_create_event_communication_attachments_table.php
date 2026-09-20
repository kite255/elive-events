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
            'event_communication_attachments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'event_communication_id'
                )
                    ->constrained(
                        'event_communications'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'title'
                );

                $table->string(
                    'file_path'
                );

                $table->string(
                    'file_type'
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
            'event_communication_attachments'
        );
    }
};
