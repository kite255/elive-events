<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->string('enquiry_label', 150)->nullable();
            $table->string('enquiry_phone', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['enquiry_label', 'enquiry_phone']);
        });
    }
};
