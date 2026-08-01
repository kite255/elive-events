<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'event_code')) {
                $table->string('event_code', 20)->nullable()->after('slug');
            }
        });

        Schema::table('attendees', function (Blueprint $table) {
            if (! Schema::hasColumn('attendees', 'event_sequence')) {
                $table->unsignedInteger('event_sequence')->nullable()->after('event_id');
            }

            if (! Schema::hasColumn('attendees', 'badge_number')) {
                $table->string('badge_number')->nullable()->after('event_sequence');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unique('event_code');
        });

        Schema::table('attendees', function (Blueprint $table) {
            $table->unique(['event_id', 'event_sequence']);
            $table->unique('badge_number');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'event_sequence']);
            $table->dropUnique(['badge_number']);
            $table->dropColumn(['event_sequence', 'badge_number']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['event_code']);
            $table->dropColumn('event_code');
        });
    }
};