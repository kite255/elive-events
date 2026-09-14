<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Ticket Orders
        |--------------------------------------------------------------------------
        |
        | Laravel stores its normal created_at / updated_at timestamps as
        | timestamp without time zone. Keep custom application datetimes
        | consistent with that behavior.
        |
        | AT TIME ZONE 'UTC' intentionally preserves the existing stored
        | wall-clock values when converting the current timestamptz columns.
        |
        */

        DB::statement("
            ALTER TABLE ticket_orders
            ALTER COLUMN expires_at
            TYPE timestamp without time zone
            USING expires_at AT TIME ZONE 'UTC'
        ");

        DB::statement("
            ALTER TABLE ticket_orders
            ALTER COLUMN paid_at
            TYPE timestamp without time zone
            USING paid_at AT TIME ZONE 'UTC'
        ");

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN initiated_at
            TYPE timestamp without time zone
            USING initiated_at AT TIME ZONE 'UTC'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN paid_at
            TYPE timestamp without time zone
            USING paid_at AT TIME ZONE 'UTC'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN fulfilled_at
            TYPE timestamp without time zone
            USING fulfilled_at AT TIME ZONE 'UTC'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN failed_at
            TYPE timestamp without time zone
            USING failed_at AT TIME ZONE 'UTC'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN cancelled_at
            TYPE timestamp without time zone
            USING cancelled_at AT TIME ZONE 'UTC'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE ticket_orders
            ALTER COLUMN expires_at
            TYPE timestamp with time zone
            USING expires_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE ticket_orders
            ALTER COLUMN paid_at
            TYPE timestamp with time zone
            USING paid_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN initiated_at
            TYPE timestamp with time zone
            USING initiated_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN paid_at
            TYPE timestamp with time zone
            USING paid_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN fulfilled_at
            TYPE timestamp with time zone
            USING fulfilled_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN failed_at
            TYPE timestamp with time zone
            USING failed_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");

        DB::statement("
            ALTER TABLE payments
            ALTER COLUMN cancelled_at
            TYPE timestamp with time zone
            USING cancelled_at AT TIME ZONE 'Africa/Dar_es_Salaam'
        ");
    }
};