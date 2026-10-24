<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_finance_clearance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('entry_type', 24);
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('TZS');
            $table->string('external_reference')->unique();
            $table->text('verification_note');
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->index(['event_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_finance_clearance_entries');
    }
};
