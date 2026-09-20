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
            $table->boolean('registration_show_phone')
                ->default(true);

            $table->boolean('registration_require_phone')
                ->default(true);

            $table->boolean('registration_show_email')
                ->default(true);

            $table->boolean('registration_require_email')
                ->default(false);

            $table->boolean('registration_show_organization')
                ->default(false);

            $table->boolean('registration_require_organization')
                ->default(false);

            $table->boolean('registration_show_position')
                ->default(false);

            $table->boolean('registration_require_position')
                ->default(false);

            $table->boolean('registration_show_category')
                ->default(false);

            $table->boolean('registration_require_category')
                ->default(false);

            $table->boolean('registration_show_badge_type')
                ->default(false);

            $table->boolean('registration_require_badge_type')
                ->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'registration_show_phone',
                'registration_require_phone',
                'registration_show_email',
                'registration_require_email',
                'registration_show_organization',
                'registration_require_organization',
                'registration_show_position',
                'registration_require_position',
                'registration_show_category',
                'registration_require_category',
                'registration_show_badge_type',
                'registration_require_badge_type',
            ]);
        });
    }
};