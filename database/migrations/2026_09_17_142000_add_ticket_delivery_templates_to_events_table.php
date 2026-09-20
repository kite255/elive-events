<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'events',
            function (Blueprint $table): void {
                $table
                    ->foreignId('ticket_delivery_email_template_id')
                    ->nullable()
                    ->constrained('communication_templates')
                    ->nullOnDelete();

                $table
                    ->foreignId('ticket_delivery_sms_template_id')
                    ->nullable()
                    ->constrained('communication_templates')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'events',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'ticket_delivery_email_template_id',
                ]);

                $table->dropForeign([
                    'ticket_delivery_sms_template_id',
                ]);

                $table->dropColumn([
                    'ticket_delivery_email_template_id',
                    'ticket_delivery_sms_template_id',
                ]);
            }
        );
    }
};
