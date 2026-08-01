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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('primary_color')
                ->nullable()
                ->after('logo_path');

            $table->string('secondary_color')
                ->nullable()
                ->after('primary_color');

            $table->string('background_color')
                ->nullable()
                ->after('secondary_color');

            $table->string('button_color')
                ->nullable()
                ->after('background_color');

            $table->string('support_email')
                ->nullable()
                ->after('website');

            $table->string('support_phone')
                ->nullable()
                ->after('support_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color',
                'secondary_color',
                'background_color',
                'button_color',
                'support_email',
                'support_phone',
            ]);
        });
    }
};