<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('vehicle_type')->default('Moto');
            $table->string('vehicle_plate')->nullable();
            $table->decimal('rating', 3, 2)->default(5);
            $table->string('status')->default('available');
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->foreignId('dropoff_place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->string('service_type')->default('eco');
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('price_fcfa');
            $table->unsignedInteger('estimated_minutes');
            $table->string('status')->default('estimating');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('parcel_size')->default('medium');
            $table->text('parcel_note')->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedInteger('price_fcfa');
            $table->unsignedInteger('estimated_minutes');
            $table->string('status')->default('estimating');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('location');
            $table->string('price_range')->default('F');
            $table->decimal('rating', 3, 2)->default(5);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('delivery_minutes')->default(30);
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->string('emoji')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->string('emoji')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('market_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_address');
            $table->unsignedInteger('subtotal_fcfa');
            $table->unsignedInteger('delivery_fee_fcfa');
            $table->unsignedInteger('total_fcfa');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('market_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_fcfa');
            $table->unsignedInteger('total_fcfa');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('label');
            $table->string('type');
            $table->string('method')->default('noki_pay');
            $table->unsignedInteger('amount_fcfa');
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('system');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('market_order_items');
        Schema::dropIfExists('market_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('places');
    }
};
