<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'communication_campaign_recipients',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('communication_campaign_id')
                    ->constrained('communication_campaigns')
                    ->cascadeOnDelete();

                $table->foreignId('attendee_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('communication_log_id')
                    ->nullable()
                    ->constrained('communication_logs')
                    ->nullOnDelete();

                $table->string('status', 30)
                    ->default('pending');

                $table->string('recipient')
                    ->nullable();

                $table->string('rendered_subject')
                    ->nullable();

                $table->text('rendered_message')
                    ->nullable();

                $table->unsignedSmallInteger('attempts')
                    ->default(0);

                $table->timestamp('queued_at')
                    ->nullable();

                $table->timestamp('sent_at')
                    ->nullable();

                $table->timestamp('delivered_at')
                    ->nullable();

                $table->timestamp('failed_at')
                    ->nullable();

                $table->text('error_message')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'communication_campaign_id',
                        'attendee_id',
                    ],
                    'campaign_attendee_unique'
                );

                $table->index([
                    'communication_campaign_id',
                    'status',
                ]);

                $table->index([
                    'attendee_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'communication_campaign_recipients'
        );
    }
};