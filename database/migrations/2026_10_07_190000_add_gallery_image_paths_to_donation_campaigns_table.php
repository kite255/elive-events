<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->json('gallery_image_paths')
                ->nullable()
                ->after('banner_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->dropColumn('gallery_image_paths');
        });
    }
};
