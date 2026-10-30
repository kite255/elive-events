<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_organizer_ticket_summary_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date');
            $table->foreignId('event_id');
            $table->foreignId('recipient_user_id');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['report_date','event_id','recipient_user_id'], 'daily_organizer_ticket_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_organizer_ticket_summary_deliveries');
    }
};
