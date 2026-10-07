<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table
                ->boolean('show_on_elive_website')
                ->default(false)
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['show_on_elive_website']);
            $table->dropColumn('show_on_elive_website');
        });
    }
};
