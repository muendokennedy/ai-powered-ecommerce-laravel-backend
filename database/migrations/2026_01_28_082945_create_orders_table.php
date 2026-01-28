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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->string('order_tracking_number');
            $table->enum('status', ['pending','processing', 'in transit', 'delivered', 'cancelled']);
            $table->decimal('shipping_cost');
            $table->string('street_address');
            $table->string('apartment/suite');
            $table->string('city/town');
            $table->string('region');
            $table->decimal('postal_code');
            $table->string('country');
            $table->longText('delivery_instructions')->nullable();
            $table->geography('coordinates');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
