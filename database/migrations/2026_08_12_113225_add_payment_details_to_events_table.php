<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('payment_method')->nullable();
            $table->string('payment_account_name')->nullable();
            $table->string('payment_account_number')->nullable();
            $table->text('payment_instructions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_account_name',
                'payment_account_number',
                'payment_instructions',
            ]);
        });
    }
};