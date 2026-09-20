<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_categories', function (Blueprint $table) {
            $table->string('group_name')
                ->nullable();

            $table->boolean('is_public')
                ->default(true)
                ->index();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->foreignId('badge_type_id')
                ->nullable()
                ->constrained('badge_types')
                ->nullOnDelete();

            $table->text('description')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendee_categories', function (Blueprint $table) {
            $table->dropForeign([
                'badge_type_id',
            ]);

            $table->dropColumn([
                'group_name',
                'is_public',
                'is_active',
                'badge_type_id',
                'description',
            ]);
        });
    }
};