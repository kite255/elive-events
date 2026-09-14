<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | PostgreSQL-Only Datetime Normalization
        |--------------------------------------------------------------------------
        |
        | This migration exists specifically to normalize PostgreSQL
        | timestamptz columns into timestamp without time zone.
        |
        | SQLite is used by the automated test suite and does not support:
        |
        | ALTER COLUMN ... TYPE ...
        | USING ... AT TIME ZONE ...
        |
        | SQLite already stores these datetime values without PostgreSQL's
        | timezone type semantics, so there is nothing to normalize there.
        |
        */

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ticket Orders
        |--------------------------------------------------------------------------
        |
        | Laravel's standard created_at / updated_at columns use timestamp
        | without time zone in PostgreSQL.
        |
        | Keep custom ticket-order datetime columns consistent with that
        | behavior.
        |
        | AT TIME ZONE 'UTC' preserves the existing UTC wall-clock values
        | while converting from timestamptz.
        |
        */

        DB::statement(<<<'SQL'
            ALTER TABLE ticket_orders
            ALTER COLUMN expires_at
            TYPE timestamp without time zone
            USING expires_at AT TIME ZONE 'UTC'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ticket_orders
            ALTER COLUMN paid_at
            TYPE timestamp without time zone
            USING paid_at AT TIME ZONE 'UTC'
        SQL);

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN initiated_at
            TYPE timestamp without time zone
            USING initiated_at AT TIME ZONE 'UTC'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN paid_at
            TYPE timestamp without time zone
            USING paid_at AT TIME ZONE 'UTC'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN fulfilled_at
            TYPE timestamp without time zone
            USING fulfilled_at AT TIME ZONE 'UTC'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN failed_at
            TYPE timestamp without time zone
            USING failed_at AT TIME ZONE 'UTC'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN cancelled_at
            TYPE timestamp without time zone
            USING cancelled_at AT TIME ZONE 'UTC'
        SQL);
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | PostgreSQL-Only Rollback
        |--------------------------------------------------------------------------
        |
        | SQLite does not perform any conversion in up(), so there is nothing
        | to reverse for SQLite.
        |
        */

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Ticket Orders
        |--------------------------------------------------------------------------
        */

        DB::statement(<<<'SQL'
            ALTER TABLE ticket_orders
            ALTER COLUMN expires_at
            TYPE timestamp with time zone
            USING expires_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ticket_orders
            ALTER COLUMN paid_at
            TYPE timestamp with time zone
            USING paid_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN initiated_at
            TYPE timestamp with time zone
            USING initiated_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN paid_at
            TYPE timestamp with time zone
            USING paid_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN fulfilled_at
            TYPE timestamp with time zone
            USING fulfilled_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN failed_at
            TYPE timestamp with time zone
            USING failed_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments
            ALTER COLUMN cancelled_at
            TYPE timestamp with time zone
            USING cancelled_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        SQL);
    }
};