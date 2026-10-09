<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('donation_campaigns', 'event_id')) {
            Schema::table('donation_campaigns', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('event_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('donation_campaigns', 'event_id')) {
            Schema::table('donation_campaigns', function (Blueprint $table): void {
                $table->foreignId('event_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
            });
        }
    }
};
