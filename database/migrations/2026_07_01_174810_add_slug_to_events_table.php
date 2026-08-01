<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
        });

        /**
         * Generate slugs for existing events.
         */
        DB::table('events')
            ->select('id', 'name')
            ->orderBy('id')
            ->get()
            ->each(function ($event) {
                $baseSlug = Str::slug($event->name ?: 'event-' . $event->id);
                $slug = $baseSlug;
                $counter = 2;

                while (
                    DB::table('events')
                        ->where('slug', $slug)
                        ->where('id', '!=', $event->id)
                        ->exists()
                ) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                DB::table('events')
                    ->where('id', $event->id)
                    ->update([
                        'slug' => $slug,
                    ]);
            });

        Schema::table('events', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};