<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_finance_clearance_entries', function (Blueprint $table): void {
            $table->foreignId('independently_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('independently_verified_at')->nullable();
            $table->text('independent_verification_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_finance_clearance_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('independently_verified_by');
            $table->dropColumn(['independently_verified_at', 'independent_verification_note']);
        });
    }
};
