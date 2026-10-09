<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('donation_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('reference', 80)->unique();
            $table->string('donor_name')->nullable();
            $table->string('donor_phone')->nullable();
            $table->string('donor_email')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('public_display_consent')->default(false);
            $table->string('payment_type', 32);
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['donation_campaign_id', 'status']);
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
