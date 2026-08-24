<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('registration_email_enabled')
                ->default(true)
                ->after('registration_sms_enabled');

            $table->boolean('registration_whatsapp_enabled')
                ->default(false)
                ->after('registration_email_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'registration_email_enabled',
                'registration_whatsapp_enabled',
            ]);
        });
    }
};