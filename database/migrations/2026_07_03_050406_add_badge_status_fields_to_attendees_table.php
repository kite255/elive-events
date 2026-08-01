<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (! Schema::hasColumn('attendees', 'badge_status')) {
                $table->string('badge_status')
                    ->default('pending')
                    ->after('badge_path');
            }

            if (! Schema::hasColumn('attendees', 'badge_generated_at')) {
                $table->timestamp('badge_generated_at')
                    ->nullable()
                    ->after('badge_status');
            }

            if (! Schema::hasColumn('attendees', 'badge_printed_at')) {
                $table->timestamp('badge_printed_at')
                    ->nullable()
                    ->after('badge_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (Schema::hasColumn('attendees', 'badge_printed_at')) {
                $table->dropColumn('badge_printed_at');
            }

            if (Schema::hasColumn('attendees', 'badge_generated_at')) {
                $table->dropColumn('badge_generated_at');
            }

            if (Schema::hasColumn('attendees', 'badge_status')) {
                $table->dropColumn('badge_status');
            }
        });
    }
};