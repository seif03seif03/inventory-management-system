<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An inventory adjustment corrects stock that no document explains: damage,
     * loss, theft, expiry, or a stocktake recount that disagrees with the
     * ledger. Stock In needs a supplier and Stock Out needs a distributor —
     * neither fits "we counted 98 and the system says 100".
     *
     * It is NOT a second stock system. A completed adjustment writes IN or OUT
     * rows into stock_movements exactly as receipts, issues and transfers do,
     * so current stock is still SUM(IN) - SUM(OUT) and nothing else.
     *
     * Because an adjustment can create or destroy stock with no counterparty,
     * it records a mandatory reason and its author.
     */
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: an adjustment is stock history, and deleting a
            // warehouse must never silently erase it.
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->string('reference_number', 100);
            $table->date('adjustment_date');

            // Why the count changed. Required — an unexplained adjustment is
            // indistinguishable from a mistake or from theft.
            $table->string('reason', 50);

            $table->text('notes')->nullable();

            // Same convention as stock_ins / stock_outs: a plain string, so a
            // future approval step can add states without a migration.
            $table->string('status', 20)->default('completed');

            // nullOnDelete: who adjusted stock stays answerable even if their
            // account is later removed, so the row survives with a null author.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('reference_number');
            $table->index('adjustment_date');
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: a line cannot exist without its document.
            $table->foreignId('inventory_adjustment_id')
                ->constrained('inventory_adjustments')
                ->cascadeOnDelete();

            // restrictOnDelete: a product that has been adjusted cannot be
            // deleted, or the document would point at nothing.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // 'increase' or 'decrease'.
            //
            // Stored separately from the quantity, which is always POSITIVE —
            // the same convention stock_movements uses, where type decides
            // whether a quantity is added or subtracted. A signed integer would
            // read more naturally in a form but would give the codebase two
            // competing ways to express direction.
            $table->string('direction', 10);
            $table->integer('quantity');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
    }
};
