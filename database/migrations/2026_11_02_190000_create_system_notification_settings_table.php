<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('payout_request_emails_enabled')->default(false);
            $table->json('payout_request_recipients')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notification_settings');
    }
};
