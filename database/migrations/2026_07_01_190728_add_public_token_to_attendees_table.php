<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (! Schema::hasColumn('attendees', 'public_token')) {
                $table->string('public_token', 80)
                    ->nullable()
                    ->unique()
                    ->after('badge_number');
            }
        });

        DB::table('attendees')
            ->whereNull('public_token')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($attendee) {
                do {
                    $token = Str::random(64);
                } while (
                    DB::table('attendees')
                        ->where('public_token', $token)
                        ->exists()
                );

                DB::table('attendees')
                    ->where('id', $attendee->id)
                    ->update([
                        'public_token' => $token,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table) {
            if (Schema::hasColumn('attendees', 'public_token')) {
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            }
        });
    }
};