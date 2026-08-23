<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * stock_out_items is the CHILD table: one row per product line on an issue.
     * An issue with 3 products = 1 stock_outs row + 3 stock_out_items rows.
     *
     * Notice: no unit_cost column here.
     * Stock Out is about quantity only — the selling price to the distributor
     * is a separate business concern that can be added later.
     */
    public function up(): void
    {
        Schema::create('stock_out_items', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete(): an item cannot exist without its issue document.
            // If the issue is ever deleted, its items are removed automatically.
            $table->foreignId('stock_out_id')->constrained('stock_outs')->cascadeOnDelete();

            // restrictOnDelete(): a product that has already been issued cannot be
            // deleted — that would leave the issue pointing at a missing product.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Always a positive integer. The type column in stock_movements (OUT)
            // is what signals that this quantity is subtracted from stock.
            $table->integer('quantity');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_out_items');
    }
};
