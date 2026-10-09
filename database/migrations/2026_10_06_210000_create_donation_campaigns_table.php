<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('payment_mode', 32);
            $table->string('direct_payment_behavior', 32)->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->decimal('minimum_amount', 14, 2)->nullable();
            $table->json('suggested_amounts')->nullable();
            $table->boolean('allow_custom_amount')->default(true);
            $table->decimal('goal_amount', 14, 2)->nullable();
            $table->boolean('show_goal')->default(false);
            $table->boolean('show_amount_raised')->default(false);
            $table->boolean('show_percentage')->default(false);
            $table->boolean('show_donor_count')->default(false);
            $table->boolean('donor_wall_enabled')->default(false);
            $table->json('notification_settings')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['status', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_campaigns');
    }
};
