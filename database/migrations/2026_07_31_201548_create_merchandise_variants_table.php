<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_merchandise_id')
                ->constrained('event_merchandises')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('sku')->nullable();

            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();

            $table->unique([
                'event_merchandise_id',
                'name',
            ]);

            $table->index([
                'event_merchandise_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_variants');
    }
};
