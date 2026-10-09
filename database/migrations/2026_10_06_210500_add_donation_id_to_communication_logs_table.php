<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_logs', function (Blueprint $table): void {
            $table->foreignId('event_id')
                ->nullable()
                ->change();

            $table->foreignId('donation_id')
                ->nullable()
                ->after('ticket_upgrade_id')
                ->constrained('donations')
                ->nullOnDelete();

            $table->index(['donation_id', 'status']);
            $table->unique(
                ['donation_id', 'purpose', 'channel'],
                'communication_logs_donation_purpose_channel_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('communication_logs', function (Blueprint $table): void {
            $table->dropUnique(
                'communication_logs_donation_purpose_channel_unique'
            );
            $table->dropIndex(['donation_id', 'status']);
            $table->dropConstrainedForeignId('donation_id');

            $table->foreignId('event_id')
                ->nullable(false)
                ->change();
        });
    }
};
