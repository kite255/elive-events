<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ticket_summary_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->string('recipient');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['report_date', 'recipient'], 'daily_ticket_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ticket_summary_deliveries');
    }
};
