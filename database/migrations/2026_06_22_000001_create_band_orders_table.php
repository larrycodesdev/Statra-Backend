<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // STR-2026-00001

            // Customer
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 30);

            // Shipping address
            $table->string('street_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();

            // Product
            $table->enum('band_size', ['S', 'M', 'L']);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->enum('plan', ['band_only', 'band_care_plan']);

            // Pricing (stored in USD)
            $table->decimal('unit_price', 8, 2);
            $table->decimal('subtotal',   8, 2);
            $table->decimal('discount',   8, 2)->default(0);
            $table->decimal('shipping',   8, 2)->default(0);
            $table->decimal('total',      8, 2);

            // Order lifecycle
            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('pending');

            // Payment
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('korapay_checkout_url')->nullable();

            // Shipping / tracking
            $table->string('tracking_number')->nullable();
            $table->string('courier')->nullable();
            $table->timestamp('shipped_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_orders');
    }
};
