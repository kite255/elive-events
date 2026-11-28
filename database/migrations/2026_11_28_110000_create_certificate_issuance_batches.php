<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_certificate_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_certificate_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('eligibility_rule', 40);
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
        });
        Schema::create('event_certificate_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_certificate_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['event_certificate_batch_id', 'attendee_id'], 'certificate_batch_attendee_unique');
            $table->index(['event_certificate_batch_id', 'status'], 'certificate_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_certificate_batch_items');
        Schema::dropIfExists('event_certificate_batches');
    }
};
