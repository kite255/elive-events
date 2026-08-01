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
            $table->boolean('registration_is_open')
                ->default(false)
                ->after('status');

            $table->boolean('registration_requires_approval')
                ->default(false)
                ->after('registration_is_open');

            $table->string('registration_banner_image_path')
                ->nullable()
                ->after('registration_requires_approval');

            $table->string('registration_logo_path')
                ->nullable()
                ->after('registration_banner_image_path');

            $table->string('registration_primary_color')
                ->nullable()
                ->after('registration_logo_path');

            $table->string('registration_background_color')
                ->nullable()
                ->after('registration_primary_color');

            $table->string('registration_button_color')
                ->nullable()
                ->after('registration_background_color');

            $table->string('registration_welcome_title')
                ->nullable()
                ->after('registration_button_color');

            $table->text('registration_welcome_message')
                ->nullable()
                ->after('registration_welcome_title');

            $table->text('registration_success_message')
                ->nullable()
                ->after('registration_welcome_message');

            $table->boolean('registration_auto_generate_badge')
                ->default(true)
                ->after('registration_success_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'registration_is_open',
                'registration_requires_approval',
                'registration_banner_image_path',
                'registration_logo_path',
                'registration_primary_color',
                'registration_background_color',
                'registration_button_color',
                'registration_welcome_title',
                'registration_welcome_message',
                'registration_success_message',
                'registration_auto_generate_badge',
            ]);
        });
    }
};