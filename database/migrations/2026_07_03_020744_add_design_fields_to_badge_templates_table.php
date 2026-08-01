<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('badge_templates', 'background_path')) {
                $table->string('background_path')->nullable()->after('name');
            }

            if (! Schema::hasColumn('badge_templates', 'width')) {
                $table->unsignedInteger('width')->default(420)->after('background_path');
            }

            if (! Schema::hasColumn('badge_templates', 'height')) {
                $table->unsignedInteger('height')->default(620)->after('width');
            }

            if (! Schema::hasColumn('badge_templates', 'design_config')) {
                $table->json('design_config')->nullable()->after('height');
            }

            if (! Schema::hasColumn('badge_templates', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('design_config');
            }

            if (! Schema::hasColumn('badge_templates', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_default');
            }
        });
    }

    public function down(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            if (Schema::hasColumn('badge_templates', 'background_path')) {
                $table->dropColumn('background_path');
            }

            if (Schema::hasColumn('badge_templates', 'width')) {
                $table->dropColumn('width');
            }

            if (Schema::hasColumn('badge_templates', 'height')) {
                $table->dropColumn('height');
            }

            if (Schema::hasColumn('badge_templates', 'design_config')) {
                $table->dropColumn('design_config');
            }

            if (Schema::hasColumn('badge_templates', 'is_default')) {
                $table->dropColumn('is_default');
            }

            if (Schema::hasColumn('badge_templates', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};