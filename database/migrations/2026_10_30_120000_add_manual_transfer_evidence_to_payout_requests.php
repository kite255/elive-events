<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_payout_requests', function (Blueprint $table): void {
            $table->string('transfer_method', 32)->nullable();
            $table->decimal('transferred_amount', 15, 2)->nullable();
            $table->date('transfer_date')->nullable();
            $table->string('transfer_proof_path')->nullable();
            $table->text('transfer_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_payout_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'transfer_method', 'transferred_amount', 'transfer_date',
                'transfer_proof_path', 'transfer_note',
            ]);
        });
    }
};
