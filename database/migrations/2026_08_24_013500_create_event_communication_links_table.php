<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'event_communication_links',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('event_communication_id')
                    ->constrained('event_communications')
                    ->cascadeOnDelete();

                $table->string('label');
                $table->text('url');

                $table
                    ->boolean('open_in_new_tab')
                    ->default(true);

                $table
                    ->unsignedInteger('sort_order')
                    ->default(0);

                $table->timestamps();

                $table->index([
                    'event_communication_id',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'event_communication_links'
        );
    }
};
