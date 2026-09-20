<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name', 100);
            $table->string('code', 50);
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('environment', 20)->default('sandbox');

            // Encrypted through the model cast.
            $table->text('configuration')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'is_enabled']);
            $table->index(['code', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
