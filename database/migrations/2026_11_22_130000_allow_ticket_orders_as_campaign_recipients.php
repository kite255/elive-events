<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_campaign_recipients', function (Blueprint $table): void {
            $table->foreignId('attendee_id')->nullable()->change();
            $table->foreignId('ticket_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['communication_campaign_id', 'ticket_order_id'], 'campaign_ticket_order_unique');
        });
    }

    public function down(): void
    {
        DB::table('communication_campaign_recipients')->whereNull('attendee_id')->delete();

        Schema::table('communication_campaign_recipients', function (Blueprint $table): void {
            $table->dropUnique('campaign_ticket_order_unique');
            $table->dropConstrainedForeignId('ticket_order_id');
            $table->foreignId('attendee_id')->nullable(false)->change();
        });
    }
};
