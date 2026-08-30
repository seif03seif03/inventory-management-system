<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['active', 'category_id'], 'products_active_category_idx');
            $table->index('name', 'products_name_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'warehouse_id', 'type'], 'movements_product_warehouse_type_idx');
            $table->index(['type', 'created_at'], 'movements_type_created_idx');
            $table->index(['warehouse_id', 'created_at'], 'movements_warehouse_created_idx');
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->index(['status', 'receipt_date'], 'stock_ins_status_date_idx');
            $table->index(['supplier_id', 'receipt_date'], 'stock_ins_supplier_date_idx');
            $table->index(['warehouse_id', 'receipt_date'], 'stock_ins_warehouse_date_idx');
        });

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->index('product_id', 'stock_in_items_product_idx');
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->index(['status', 'issue_date'], 'stock_outs_status_date_idx');
            $table->index(['distributor_id', 'issue_date'], 'stock_outs_distributor_date_idx');
            $table->index(['warehouse_id', 'issue_date'], 'stock_outs_warehouse_date_idx');
        });

        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->index('product_id', 'stock_out_items_product_idx');
        });

        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->index(['status', 'transfer_date'], 'transfers_status_date_idx');
            $table->index(['from_warehouse_id', 'transfer_date'], 'transfers_from_date_idx');
            $table->index(['to_warehouse_id', 'transfer_date'], 'transfers_to_date_idx');
        });

        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->index('product_id', 'transfer_items_product_idx');
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->index(['warehouse_id', 'adjustment_date'], 'adjustments_warehouse_date_idx');
            $table->index(['reason', 'adjustment_date'], 'adjustments_reason_date_idx');
        });

        Schema::table('inventory_adjustment_items', function (Blueprint $table) {
            $table->index('product_id', 'adjustment_items_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustment_items', function (Blueprint $table) {
            $table->dropIndex('adjustment_items_product_idx');
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropIndex('adjustments_warehouse_date_idx');
            $table->dropIndex('adjustments_reason_date_idx');
        });

        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->dropIndex('transfer_items_product_idx');
        });

        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->dropIndex('transfers_status_date_idx');
            $table->dropIndex('transfers_from_date_idx');
            $table->dropIndex('transfers_to_date_idx');
        });

        Schema::table('stock_out_items', function (Blueprint $table) {
            $table->dropIndex('stock_out_items_product_idx');
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropIndex('stock_outs_status_date_idx');
            $table->dropIndex('stock_outs_distributor_date_idx');
            $table->dropIndex('stock_outs_warehouse_date_idx');
        });

        Schema::table('stock_in_items', function (Blueprint $table) {
            $table->dropIndex('stock_in_items_product_idx');
        });

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropIndex('stock_ins_status_date_idx');
            $table->dropIndex('stock_ins_supplier_date_idx');
            $table->dropIndex('stock_ins_warehouse_date_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('movements_product_warehouse_type_idx');
            $table->dropIndex('movements_type_created_idx');
            $table->dropIndex('movements_warehouse_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_category_idx');
            $table->dropIndex('products_name_idx');
        });
    }
};
