<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('organization_id')
                ->constrained('events')->nullOnDelete();
            $table->index(['event_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->dropIndex(['event_id', 'organization_id']);
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
