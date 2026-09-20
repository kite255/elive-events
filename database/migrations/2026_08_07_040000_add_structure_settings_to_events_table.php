<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('schedule_mode')
                ->default('single_day');

            $table->boolean(
                'registration_allow_day_selection'
            )->default(true);

            $table->boolean(
                'registration_allow_all_days'
            )->default(true);

            $table->boolean('sessions_enabled')
                ->default(true);

            $table->boolean(
                'session_registration_enabled'
            )->default(true);

            $table->boolean(
                'session_check_in_enabled'
            )->default(true);

            $table->index('schedule_mode');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex([
                'schedule_mode',
            ]);

            $table->dropColumn([
                'schedule_mode',
                'registration_allow_day_selection',
                'registration_allow_all_days',
                'sessions_enabled',
                'session_registration_enabled',
                'session_check_in_enabled',
            ]);
        });
    }
};
