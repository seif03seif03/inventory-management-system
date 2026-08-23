<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * stock_movements is the LEDGER of the whole inventory system.
     *
     * Every completed Stock In writes one IN row per item.
     * Every completed Stock Out writes one OUT row per item.
     *
     * Current stock is never stored anywhere — it is always calculated:
     *
     *     current stock = SUM(IN quantity) - SUM(OUT quantity)
     *
     * ...filtered by product_id AND warehouse_id, because the same product
     * can hold different quantities in different warehouses.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Stock is always tracked per PRODUCT + WAREHOUSE pair.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            // 'IN' or 'OUT'. Quantity is always stored as a POSITIVE number;
            // the type column decides whether it is added or subtracted.
            $table->string('type', 3);
            $table->integer('quantity');

            // Which document created this movement, so the UI can show
            // "Stock In #1001". reference_type is 'stock_in' or 'stock_out',
            // reference_id is that record's id.
            // (Laravel's $table->nullableMorphs('reference') would create these two
            // columns for us, but we write them out so the design stays obvious.)
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();

            // The stock calculation always filters on product + warehouse,
            // so an index on that pair keeps it fast as the ledger grows.
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
