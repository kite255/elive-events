<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_check_ins', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ticket_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('check_in_point_id')
                ->nullable()
                ->constrained('check_in_points')
                ->nullOnDelete();

            $table->foreignId('checked_in_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('method')
                ->default('qr');

            $table->dateTime('checked_in_at');

            $table->string('device_name')
                ->nullable();

            $table->string('ip_address')
                ->nullable();

            $table->text('note')
                ->nullable();

            $table->timestamps();

            /*
             * A concert ticket is single-entry for the MVP.
             *
             * This unique constraint is the database-level duplicate
             * protection if two scanners try to accept the same ticket
             * at nearly the same time.
             */
            $table->unique(
                'ticket_id',
                'ticket_check_ins_ticket_unique'
            );

            $table->index(
                ['event_id', 'checked_in_at'],
                'ticket_check_ins_event_time_index'
            );

            $table->index(
                ['event_id', 'check_in_point_id'],
                'ticket_check_ins_event_point_index'
            );

            $table->index(
                'checked_in_by',
                'ticket_check_ins_checked_in_by_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_check_ins');
    }
};
