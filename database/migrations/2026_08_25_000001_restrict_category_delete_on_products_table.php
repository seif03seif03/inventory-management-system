<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * products.category_id was created with cascadeOnDelete(), which meant
     * deleting a category silently deleted every product inside it — and with
     * them, the products' place in the stock ledger. Products are never
     * disposable collateral of a category delete.
     *
     * RESTRICT matches how every other reference to a product already behaves
     * (stock_movements, stock_in_items, stock_out_items and
     * warehouse_transfer_items are all RESTRICT), so the database now refuses
     * the delete and CategoryController explains why instead.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });
    }
};
