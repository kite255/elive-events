<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_certificate_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_certificate_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 10);
            $table->text('recipient_encrypted');
            $table->string('recipient_hash', 64);
            $table->string('status', 30)->default('queued');
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['event_certificate_id', 'channel'], 'certificate_delivery_channel_unique');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_certificate_deliveries');
    }
};
