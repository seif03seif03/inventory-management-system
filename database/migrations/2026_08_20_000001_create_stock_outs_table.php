<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * stock_outs is the PARENT table for a stock issue (product leaving warehouse).
     * It stores "who / where / when" — one row per issue document.
     * The actual products issued live in stock_out_items (the child table).
     *
     * This mirrors the stock_ins table design exactly:
     *   stock_ins  → products arriving   (from Supplier)
     *   stock_outs → products leaving    (to Distributor)
     */
    public function up(): void
    {
        Schema::create('stock_outs', function (Blueprint $table) {
            $table->id();

            // Who we sent the goods TO, and WHICH warehouse they came out of.
            // restrictOnDelete(): the database refuses to delete a distributor or
            // warehouse that is still referenced by an issue document.
            // We NEVER cascade-delete here — deleting a distributor must not
            // silently erase stock history.
            $table->foreignId('distributor_id')->constrained('distributors')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->string('reference_number', 100);
            $table->date('issue_date');
            $table->text('notes')->nullable();

            // Same three-state workflow as stock_ins:
            //   pending   → saved but not yet counted against stock
            //   completed → affects stock (OUT movements are created)
            //   cancelled → does not affect stock
            // Stored as a plain string so we can add states later without a migration.
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            // We search issues by reference number on the index page.
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_outs');
    }
};
