<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_user', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('role');
            $table->string('status')->default('active');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['event_id', 'user_id'],
                'event_user_unique'
            );

            $table->index([
                'event_id',
                'role',
                'status',
            ]);

            $table->index([
                'user_id',
                'role',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_user');
    }
};