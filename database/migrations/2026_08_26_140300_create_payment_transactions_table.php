<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type', 50);
            $table->string('provider_reference', 150)->nullable()->index();

            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();

            $table->string('status', 30)->default('recorded');
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'type']);
            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
