<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_manual_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('donation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('donation_payment_method_id')->constrained()->restrictOnDelete();
            $table->string('transaction_reference');
            $table->string('proof_path')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['donation_payment_method_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_manual_submissions');
    }
};
