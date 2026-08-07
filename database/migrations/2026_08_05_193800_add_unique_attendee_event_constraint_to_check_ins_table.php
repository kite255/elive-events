<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->unique(
                ['event_id', 'attendee_id'],
                'check_ins_event_attendee_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->dropUnique(
                'check_ins_event_attendee_unique'
            );
        });
    }
};