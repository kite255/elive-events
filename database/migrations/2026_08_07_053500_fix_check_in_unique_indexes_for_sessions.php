<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE check_ins
            DROP CONSTRAINT IF EXISTS check_ins_event_attendee_unique
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE check_ins
            DROP CONSTRAINT IF EXISTS check_ins_attendee_day_point_unique
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS check_ins_unique_event_entry
            ON check_ins (
                event_id,
                attendee_id,
                COALESCE(check_in_point_id, 0)
            )
            WHERE event_day_id IS NULL
              AND event_session_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS check_ins_unique_day_entry
            ON check_ins (
                event_id,
                attendee_id,
                event_day_id,
                COALESCE(check_in_point_id, 0)
            )
            WHERE event_day_id IS NOT NULL
              AND event_session_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS check_ins_unique_session_entry
            ON check_ins (
                event_id,
                attendee_id,
                event_day_id,
                event_session_id,
                COALESCE(check_in_point_id, 0)
            )
            WHERE event_session_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_session_entry'
        );

        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_day_entry'
        );

        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_event_entry'
        );

        DB::statement(<<<'SQL'
            ALTER TABLE check_ins
            ADD CONSTRAINT check_ins_event_attendee_unique
            UNIQUE (event_id, attendee_id)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE check_ins
            ADD CONSTRAINT check_ins_attendee_day_point_unique
            UNIQUE (
                attendee_id,
                event_day_id,
                check_in_point_id
            )
        SQL);
    }
};