<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_payment_methods', function (Blueprint $table): void {
            $table->string('account_identifier_type', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('donation_payment_methods', function (Blueprint $table): void {
            $table->dropColumn('account_identifier_type');
        });
    }
};
