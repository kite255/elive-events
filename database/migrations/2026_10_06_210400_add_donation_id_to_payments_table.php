<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('event_id')
                ->nullable()
                ->change();

            $table->foreignId('donation_id')
                ->nullable()
                ->after('ticket_upgrade_id')
                ->constrained('donations')
                ->nullOnDelete();

            $table->index(['donation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['donation_id', 'status']);
            $table->dropConstrainedForeignId('donation_id');

            $table->foreignId('event_id')
                ->nullable(false)
                ->change();
        });
    }
};
