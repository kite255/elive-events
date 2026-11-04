<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_finance_clearance_entries', function (Blueprint $table): void {
            $table->foreignId('self_attested_by')->nullable();
            $table->timestamp('self_attested_at')->nullable();
            $table->text('self_attestation_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_finance_clearance_entries', function (Blueprint $table): void {
            $table->dropColumn(['self_attested_by', 'self_attested_at', 'self_attestation_note']);
        });
    }
};
