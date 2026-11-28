<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_certificate_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('eligibility_rule', 40)->default('checked_in');
            $table->timestamps();
        });

        Schema::create('event_certificate_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('width')->default(1123);
            $table->unsignedInteger('height')->default(794);
            $table->json('definition')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('event_certificate_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_certificate_template_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 30);
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->timestamps();
        });

        Schema::create('event_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendee_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_certificate_template_id')->constrained()->restrictOnDelete();
            $table->string('certificate_number', 80)->unique();
            $table->string('verification_token', 64)->unique();
            $table->string('status', 30)->default('pending');
            $table->json('issuance_snapshot');
            $table->string('pdf_path')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'attendee_id']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('event_certificate_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_certificate_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_certificate_audits');
        Schema::dropIfExists('event_certificates');
        Schema::dropIfExists('event_certificate_assets');
        Schema::dropIfExists('event_certificate_templates');
        Schema::dropIfExists('event_certificate_settings');
    }
};
