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
            $table->boolean('registration_sms_enabled')
                ->default(false);

            $table->foreignId('registration_sms_template_id')
                ->nullable()
                ->constrained('communication_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign([
                'registration_sms_template_id',
            ]);

            $table->dropColumn([
                'registration_sms_enabled',
                'registration_sms_template_id',
            ]);
        });
    }
};