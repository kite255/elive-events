<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_print_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attendee_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('printed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('copies')->default(1);

            $table->string('printer_name')->nullable();

            $table->string('print_type')->default('first_print');

            $table->text('reprint_reason')->nullable();

            $table->timestamp('printed_at');

            $table->timestamps();

            $table->index(['event_id', 'printed_at']);
            $table->index(['attendee_id', 'printed_at']);
            $table->index(['printed_by', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_print_logs');
    }
};
