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
        Schema::table('check_ins', function (Blueprint $table): void {
            /*
             * The original unique constraint prevents the same attendee from
             * checking in at the same point on different event days.
             *
             * Replace it with a day-aware constraint.
             */
            $table->dropUnique([
                'attendee_id',
                'check_in_point_id',
            ]);
        });

        Schema::table('check_ins', function (Blueprint $table): void {
            $table->foreignId('event_day_id')
                ->nullable()
                ->after('event_id')
                ->constrained('event_days')
                ->nullOnDelete();

            $table->index(
                ['event_id', 'event_day_id', 'checked_in_at'],
                'check_ins_event_day_time_index'
            );

            $table->index(
                ['event_day_id', 'attendee_id'],
                'check_ins_day_attendee_index'
            );

            /*
             * One attendee may check in once at a particular check-in point
             * for a particular event day.
             *
             * event_day_id remains nullable for existing one-day events and
             * legacy check-in records.
             */
            $table->unique(
                [
                    'attendee_id',
                    'event_day_id',
                    'check_in_point_id',
                ],
                'check_ins_attendee_day_point_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->dropUnique(
                'check_ins_attendee_day_point_unique'
            );

            $table->dropIndex(
                'check_ins_event_day_time_index'
            );

            $table->dropIndex(
                'check_ins_day_attendee_index'
            );

            $table->dropConstrainedForeignId(
                'event_day_id'
            );
        });

        Schema::table('check_ins', function (Blueprint $table): void {
            $table->unique([
                'attendee_id',
                'check_in_point_id',
            ]);
        });
    }
};
