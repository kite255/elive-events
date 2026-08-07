<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->foreignId('event_session_id')
                ->nullable()
                ->constrained('event_sessions')
                ->nullOnDelete();

            $table->index(
                [
                    'event_id',
                    'event_day_id',
                    'event_session_id',
                    'checked_in_at',
                ],
                'check_ins_session_time_index'
            );

            $table->index(
                [
                    'event_session_id',
                    'attendee_id',
                ],
                'check_ins_session_attendee_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->dropIndex(
                'check_ins_session_time_index'
            );

            $table->dropIndex(
                'check_ins_session_attendee_index'
            );

            $table->dropConstrainedForeignId(
                'event_session_id'
            );
        });
    }
};
