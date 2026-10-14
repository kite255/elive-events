<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchandise_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('price_tzs')->nullable();
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'slug']);
        });

        Schema::create('merchandise_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchandise_product_id')->constrained()->restrictOnDelete();
            $table->string('public_token', 64)->unique();
            $table->string('order_number')->unique();
            $table->string('buyer_name');
            $table->string('buyer_phone');
            $table->string('buyer_email')->nullable();
            $table->string('color');
            $table->string('size', 12);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('unit_price_tzs');
            $table->unsignedBigInteger('total_tzs');
            $table->string('fulfillment_method')->default('event_pickup');
            $table->string('status')->default('pending_payment');
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_orders');
        Schema::dropIfExists('merchandise_products');
    }
};
