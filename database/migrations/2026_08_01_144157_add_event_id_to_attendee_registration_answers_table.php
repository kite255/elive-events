<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_registration_answers', function (Blueprint $table): void {
            $table->foreignId('event_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index(
                ['event_id', 'attendee_id'],
                'attendee_answers_event_attendee_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendee_registration_answers', function (Blueprint $table): void {
            $table->dropIndex('attendee_answers_event_attendee_index');
            $table->dropConstrainedForeignId('event_id');
        });
    }
};