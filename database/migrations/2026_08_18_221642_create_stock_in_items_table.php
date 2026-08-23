<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * stock_in_items is the CHILD table: one row per product line on a receipt.
     * A receipt with 3 products = 1 stock_ins row + 3 stock_in_items rows.
     */
    public function up(): void
    {
        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete(): an item cannot exist without its receipt, so if the
            // receipt row is ever deleted the database removes its items automatically.
            $table->foreignId('stock_in_id')->constrained('stock_ins')->cascadeOnDelete();

            // restrictOnDelete(): a product that has already been received cannot be
            // deleted, because that would leave the receipt pointing at nothing.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->integer('quantity');

            // decimal(10, 2) = up to 99,999,999.99 — matches products.price.
            // NEVER use float/double for money; floats lose precision.
            $table->decimal('unit_cost', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_in_items');
    }
};
