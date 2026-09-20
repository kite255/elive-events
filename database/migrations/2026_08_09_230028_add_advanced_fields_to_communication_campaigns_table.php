<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_campaigns', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('communication_template_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('communication_campaigns', 'type')) {
                $table->string('type', 50)
                    ->nullable()
                    ->after('channel');
            }

            if (! Schema::hasColumn('communication_campaigns', 'subject')) {
                $table->string('subject')
                    ->nullable()
                    ->after('type');
            }

            if (! Schema::hasColumn('communication_campaigns', 'message')) {
                $table->text('message')
                    ->nullable()
                    ->after('subject');
            }

            if (! Schema::hasColumn('communication_campaigns', 'recipient_filter')) {
                $table->json('recipient_filter')
                    ->nullable()
                    ->after('status');
            }

            if (! Schema::hasColumn('communication_campaigns', 'queued_count')) {
                $table->unsignedInteger('queued_count')
                    ->default(0)
                    ->after('total_recipients');
            }

            if (! Schema::hasColumn('communication_campaigns', 'delivered_count')) {
                $table->unsignedInteger('delivered_count')
                    ->default(0)
                    ->after('sent_count');
            }

            if (! Schema::hasColumn('communication_campaigns', 'completed_at')) {
                $table->timestamp('completed_at')
                    ->nullable()
                    ->after('sent_at');
            }

            if (! Schema::hasColumn('communication_campaigns', 'cancelled_at')) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('communication_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('communication_campaigns', 'created_by')) {
                $table->dropForeign(['created_by']);
            }

            $columns = [];

            foreach ([
                'created_by',
                'type',
                'subject',
                'message',
                'recipient_filter',
                'queued_count',
                'delivered_count',
                'completed_at',
                'cancelled_at',
            ] as $column) {
                if (Schema::hasColumn('communication_campaigns', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};