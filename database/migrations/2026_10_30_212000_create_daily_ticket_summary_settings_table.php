<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_ticket_summary_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('admin_enabled')->default(false);
            $table->json('admin_recipients')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ticket_summary_settings');
    }
};
