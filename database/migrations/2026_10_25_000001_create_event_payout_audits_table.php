<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_payout_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_payout_request_id')->constrained('event_payout_requests')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_payout_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payout_audits');
    }
};
