<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_payment_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('payments_enabled')->default(false);
            $table->string('currency', 3)->default('TZS');
            $table->decimal('registration_fee', 15, 2)->default(0);

            $table->boolean('payment_required_before_confirmation')
                ->default(true);

            $table->boolean('payment_required_before_badge')
                ->default(true);

            $table->boolean('block_check_in_if_unpaid')
                ->default(false);

            $table->boolean('allow_manual_payment')
                ->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payment_settings');
    }
};
