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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_sku_id');
            $table->string('name');
            $table->string('brand');
            $table->longText('description');
            $table->json('specifications');
            $table->foreignId('category_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('base_price');
            $table->decimal('discount_price');
            $table->double('vat_rate')->nullable();
            $table->enum('status', ['in stock', 'out of stock', 'low stock']); // later include and enum class instead; Stock::cases()
            $table->decimal('stock_quantity');
            $table->decimal('low_stock_threshold');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
