<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('fulfilled_at')
                ->nullable()
                ->after('paid_at');

            $table->index('fulfilled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['fulfilled_at']);
            $table->dropColumn('fulfilled_at');
        });
    }
};