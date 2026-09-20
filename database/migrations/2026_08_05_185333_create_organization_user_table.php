<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->boolean('is_owner')->default(false);

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'user_id'],
                'organization_user_unique'
            );

            $table->index(['organization_id', 'role']);
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};