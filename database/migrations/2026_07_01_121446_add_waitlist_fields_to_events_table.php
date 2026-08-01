<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('registration_waitlist_enabled')
                ->default(false)
                ->after('registration_auto_generate_badge');

            $table->text('registration_waitlist_message')
                ->nullable()
                ->after('registration_waitlist_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'registration_waitlist_enabled',
                'registration_waitlist_message',
            ]);
        });
    }
};