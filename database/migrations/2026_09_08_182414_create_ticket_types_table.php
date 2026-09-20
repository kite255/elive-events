<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->string('code', 30);

            $table
                ->text('description')
                ->nullable();

            $table
                ->decimal('price', 14, 2)
                ->default(0);

            $table
                ->string('currency', 3)
                ->default('TZS');

            $table
                ->unsignedInteger('capacity')
                ->nullable();

            $table
                ->unsignedSmallInteger('min_per_order')
                ->default(1);

            $table
                ->unsignedSmallInteger('max_per_order')
                ->default(10);

            $table
                ->timestampTz('sales_start_at')
                ->nullable();

            $table
                ->timestampTz('sales_end_at')
                ->nullable();

            $table
                ->boolean('is_active')
                ->default(true);

            $table
                ->boolean('is_public')
                ->default(true);

            $table
                ->boolean('requires_holder_details')
                ->default(false);

            $table
                ->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'event_id',
                'code',
            ]);

            $table->index([
                'event_id',
                'is_active',
                'is_public',
            ]);

            $table->index([
                'event_id',
                'sales_start_at',
                'sales_end_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};