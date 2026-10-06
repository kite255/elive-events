<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('donation_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('provider_name');
            $table->string('account_name')->nullable();
            $table->string('account_number_or_phone')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['donation_campaign_id', 'enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_payment_methods');
    }
};
