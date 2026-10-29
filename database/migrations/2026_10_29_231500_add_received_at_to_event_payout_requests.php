<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_payout_requests', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_payout_requests', function (Blueprint $table): void {
            $table->dropColumn('received_at');
        });
    }
};
