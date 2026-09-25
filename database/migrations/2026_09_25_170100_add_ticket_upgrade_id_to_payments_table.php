<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('ticket_upgrade_id')
                ->nullable()
                ->after('ticket_order_id')
                ->constrained('ticket_upgrades')
                ->nullOnDelete();

            $table->index(['ticket_upgrade_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['ticket_upgrade_id', 'status']);
            $table->dropConstrainedForeignId('ticket_upgrade_id');
        });
    }
};
