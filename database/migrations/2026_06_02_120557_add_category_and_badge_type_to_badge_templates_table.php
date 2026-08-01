<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('event_id')
                ->constrained('attendee_categories')
                ->nullOnDelete();

            $table->foreignId('badge_type_id')
                ->nullable()
                ->after('category_id')
                ->constrained('badge_types')
                ->nullOnDelete();

            $table->index(['event_id', 'category_id']);
            $table->index(['event_id', 'badge_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'category_id']);
            $table->dropIndex(['event_id', 'badge_type_id']);

            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('badge_type_id');
        });
    }
};