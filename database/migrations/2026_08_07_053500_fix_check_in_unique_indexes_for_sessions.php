<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        /*
        |--------------------------------------------------------------------------
        | Remove Previous Unique Rules
        |--------------------------------------------------------------------------
        |
        | PostgreSQL creates Laravel unique constraints as table constraints.
        | SQLite represents them as unique indexes.
        |
        | The application uses PostgreSQL in production/staging and SQLite
        | for automated tests, so each database needs the appropriate syntax.
        |
        */

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE check_ins
                DROP CONSTRAINT IF EXISTS check_ins_event_attendee_unique
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE check_ins
                DROP CONSTRAINT IF EXISTS check_ins_attendee_day_point_unique
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::statement(
                'DROP INDEX IF EXISTS check_ins_event_attendee_unique'
            );

            DB::statement(
                'DROP INDEX IF EXISTS check_ins_attendee_day_point_unique'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Event-Level Check-In
        |--------------------------------------------------------------------------
        |
        | Used when there is no event day and no session.
        |
        | COALESCE(check_in_point_id, 0) ensures that NULL check-in points are
        | treated consistently for duplicate-entry protection.
        |
        */

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

        /*
        |--------------------------------------------------------------------------
        | Event-Day Check-In
        |--------------------------------------------------------------------------
        |
        | Allows the same attendee to enter on different event days while
        | preventing duplicate entry for the same day/check-in point.
        |
        */

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

        /*
        |--------------------------------------------------------------------------
        | Session Check-In
        |--------------------------------------------------------------------------
        |
        | Allows attendees to check in independently for different sessions
        | while preventing duplicate entry into the same session.
        |
        */

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
        $driver = DB::getDriverName();

        /*
        |--------------------------------------------------------------------------
        | Remove Session-Aware Unique Indexes
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_session_entry'
        );

        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_day_entry'
        );

        DB::statement(
            'DROP INDEX IF EXISTS check_ins_unique_event_entry'
        );

        /*
        |--------------------------------------------------------------------------
        | Restore Previous Unique Rules
        |--------------------------------------------------------------------------
        */

        if ($driver === 'pgsql') {
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
        } elseif ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS check_ins_event_attendee_unique
                ON check_ins (
                    event_id,
                    attendee_id
                )
            SQL);

            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS check_ins_attendee_day_point_unique
                ON check_ins (
                    attendee_id,
                    event_day_id,
                    check_in_point_id
                )
            SQL);
        }
    }
};