<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('social_share_image_path')
                ->nullable()
                ->after('registration_banner_image_path');

            $table->string('social_share_title')
                ->nullable()
                ->after('social_share_image_path');

            $table->text('social_share_description')
                ->nullable()
                ->after('social_share_title');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'social_share_image_path',
                'social_share_title',
                'social_share_description',
            ]);
        });
    }
};
