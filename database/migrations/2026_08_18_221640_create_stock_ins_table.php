<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * stock_ins is the PARENT table of a stock receipt.
     * It stores "who / where / when" — one row per receipt.
     * The actual products received live in stock_in_items (the child table).
     */
    public function up(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();

            // Who we received the goods FROM, and WHICH warehouse they went into.
            // restrictOnDelete() = the database refuses to delete a supplier/warehouse
            // that is still referenced by a receipt. We do NOT use cascadeOnDelete()
            // here, because deleting a supplier must never silently erase stock history.
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->string('reference_number', 100);
            $table->date('receipt_date');
            $table->text('notes')->nullable();

            // pending | completed | cancelled
            // Stored as a plain string (not an SQL enum) so we can add new statuses
            // later without writing a migration to alter the column.
            // Only 'completed' receipts affect stock.
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            // We search receipts by reference number on the index page.
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ins');
    }
};
