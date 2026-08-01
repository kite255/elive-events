<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_merchandises', function (
            Blueprint $table
        ): void {
            if (! Schema::hasColumn(
                'attendee_merchandises',
                'paid_at'
            )) {
                $table->timestamp('paid_at')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendee_merchandises', function (
            Blueprint $table
        ): void {
            if (Schema::hasColumn(
                'attendee_merchandises',
                'paid_at'
            )) {
                $table->dropColumn('paid_at');
            }
        });
    }
};